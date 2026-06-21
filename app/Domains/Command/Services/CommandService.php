<?php

declare(strict_types=1);

namespace App\Domains\Command\Services;

use App\Domains\Command\DTOs\CreateAgencyData;
use App\Domains\Command\DTOs\CreateCommandData;
use App\Domains\Command\Events\CommandIncidentActioned;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Command\Support\Enums\CommandIncidentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owns agency + command onboarding and the command-incident lifecycle.
 *
 * Stateless final readonly service. Writes run in a transaction; the incident
 * action path locks + re-reads the row, then validates the transition against
 * an explicit map (illegal transitions abort 422; terminal states are sealed).
 */
final readonly class CommandService
{
    /**
     * Allowed status transitions, keyed by the action verb. resolved/stood_down
     * are terminal (no key maps INTO them except as targets, and once reached no
     * action is permitted). Mirrors the forward-only lifecycle in seccmd.js,
     * with escalate as a same-status priority bump that does not advance status.
     *
     * @var array<string, array{from: list<string>, to: string|null}>
     */
    private const TRANSITIONS = [
        'acknowledge' => ['from' => ['new'], 'to' => 'acknowledged'],
        'en_route' => ['from' => ['new', 'acknowledged'], 'to' => 'en_route'],
        'on_scene' => ['from' => ['acknowledged', 'en_route'], 'to' => 'on_scene'],
        'resolve' => ['from' => ['acknowledged', 'en_route', 'on_scene'], 'to' => 'resolved'],
        'stand_down' => ['from' => ['new', 'acknowledged', 'en_route', 'on_scene'], 'to' => 'stood_down'],
        // escalate does not change status — it is a non-terminal priority flag
        // hook; allowed from any open state.
        'escalate' => ['from' => ['new', 'acknowledged', 'en_route', 'on_scene'], 'to' => null],
    ];

    public function createAgency(CreateAgencyData $data): Agency
    {
        return DB::transaction(static fn (): Agency => Agency::create([
            'code' => $data->code,
            'name' => $data->name,
            'country' => $data->country,
            'categories' => $data->categories,
            'hotline' => $data->hotline,
            'color' => $data->color,
            'logo_url' => $data->logoUrl,
            'status' => $data->status,
        ]));
    }

    public function createCommand(CreateCommandData $data): Command
    {
        return DB::transaction(static fn (): Command => Command::create([
            'agency_id' => $data->agencyId,
            'parent_id' => $data->parentId,
            'tier' => $data->tier,
            'name' => $data->name,
            'area' => $data->area,
            'lat' => $data->lat,
            'lng' => $data->lng,
        ]));
    }

    /**
     * Advance an incident's lifecycle. Concurrency-safe: the row is locked and
     * re-read inside the transaction before the transition is validated against
     * the explicit map. Illegal transitions abort 422.
     */
    public function act(CommandIncident $incident, string $action, ?string $actorId = null): CommandIncident
    {
        return DB::transaction(function () use ($incident, $action, $actorId): CommandIncident {
            /** @var CommandIncident $locked */
            $locked = CommandIncident::query()
                ->whereKey($incident->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $rule = self::TRANSITIONS[$action] ?? null;

            abort_if($rule === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'unknown_action');

            // Terminal states reject all actions.
            abort_if(
                $locked->status->isTerminal(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'incident_'.$locked->status->value
            );

            abort_unless(
                in_array($locked->status->value, $rule['from'], true),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'illegal_transition'
            );

            if ($rule['to'] !== null) {
                $target = CommandIncidentStatus::from($rule['to']);
                $locked->status = $target;

                if ($target === CommandIncidentStatus::Resolved) {
                    $locked->resolved_at = now();
                }
            }

            $locked->save();

            event(new CommandIncidentActioned($locked, $action, $actorId));

            return $locked;
        });
    }

    /**
     * Resolve a Sentrix AI responder key (npf / frsc / ffs / …) to an onboarded
     * agency. Lookup is by CODE (uppercased) — never by id — so a non-uuid key
     * can never reach the uuid id column on Postgres.
     *
     * @return array{matched: bool, agency: Agency|null, key: string, label: string}
     */
    public function resolveAgencyKey(string $key, string $country = 'NG'): array
    {
        $code = strtoupper(trim($key));
        $country = strtoupper(trim($country)) ?: 'NG';

        /** @var Agency|null $agency */
        $agency = Agency::query()
            ->where('code', $code)
            ->where('country', $country)
            ->first();

        if (! $agency instanceof Agency) {
            // Try any country as a back-compat fallback before giving up.
            $agency = Agency::query()->where('code', $code)->first();
        }

        return [
            'matched' => $agency instanceof Agency,
            'agency' => $agency,
            'key' => $code,
            'label' => $agency instanceof Agency ? $agency->name : 'Unrecognised responder ('.$code.')',
        ];
    }

    /**
     * Situational rollup: counts by status / category / severity plus headline
     * open / critical / 24h totals. Read-only computed snapshot, no Resource.
     *
     * @return array{
     *     total: int,
     *     open: int,
     *     critical: int,
     *     on_scene: int,
     *     last_24h: int,
     *     by_status: array<string, int>,
     *     by_category: array<string, int>,
     *     by_severity: array<string, int>
     * }
     */
    public function overview(): array
    {
        $byStatus = $this->countGroup('status');
        $byCategory = $this->countGroup('category');
        $bySeverity = $this->countGroup('severity');

        $total = array_sum($byStatus);
        $resolvedLike = ($byStatus[CommandIncidentStatus::Resolved->value] ?? 0)
            + ($byStatus[CommandIncidentStatus::StoodDown->value] ?? 0);

        return [
            'total' => $total,
            'open' => $total - $resolvedLike,
            'critical' => $bySeverity['critical'] ?? 0,
            'on_scene' => $byStatus[CommandIncidentStatus::OnScene->value] ?? 0,
            'last_24h' => CommandIncident::query()->where('opened_at', '>=', now()->subDay())->count(),
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_severity' => $bySeverity,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countGroup(string $column): array
    {
        /** @var array<string, int> $rows */
        $rows = CommandIncident::query()
            ->selectRaw("{$column} as bucket, count(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'bucket')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        return $rows;
    }
}
