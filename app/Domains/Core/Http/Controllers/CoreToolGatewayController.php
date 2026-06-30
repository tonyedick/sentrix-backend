<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Support\Enums\EmergencyStatus;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\Responder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sentrix Core tool gateway.
 *
 * SentrixCore's agent tools call a product backend over the Omni tool contract:
 *
 *   POST {base}/api/tools/{name}   body: { args, orgId, confirmed }
 *   auth: X-Service-Token (machine identity)   ->   returns { data: ... }
 *
 * This controller implements that contract on the Sentrix backend so the
 * assistant's tools operate on LIVE data instead of honest-failing. It is a thin
 * READ adapter for now: each supported tool maps Core's Omni-shaped request to a
 * real, org-scoped query. Authorization is already enforced upstream (the user at
 * /core/act, then Core's own least-privilege scope check); this surface trusts
 * the service token and scopes strictly by the posted orgId.
 *
 * Write tools (create_incident, dispatch_responder, escalate) are intentionally
 * NOT handled here yet — they require an acting user + the assignment flow and
 * are the next slice. Unknown/unhandled tools return 404 so Core degrades
 * gracefully (its handler then reports the action couldn't be confirmed).
 */
final class CoreToolGatewayController extends Controller
{
    /** Read tools this gateway can answer with live data. */
    private const SUPPORTED = ['get_alerts', 'list_incidents', 'command_overview'];

    public function invoke(Request $request, string $tool): JsonResponse
    {
        if (! in_array($tool, self::SUPPORTED, true)) {
            abort(Response::HTTP_NOT_FOUND, "Tool '{$tool}' is not handled by this gateway.");
        }

        /** @var array<string, mixed> $args */
        $args = (array) $request->input('args', []);
        $organizationId = $this->resolveOrganizationId((string) $request->input('orgId', ''));

        // Unknown tenant: return an empty-but-valid payload (never invent data).
        if ($organizationId === null) {
            return response()->json(['data' => $this->emptyFor($tool)]);
        }

        $data = match ($tool) {
            'get_alerts' => $this->getAlerts($organizationId, $args),
            'list_incidents' => $this->listIncidents($organizationId, $args),
            'command_overview' => $this->commandOverview($organizationId),
            default => $this->emptyFor($tool), // unreachable (guarded above)
        };

        return response()->json(['data' => $data]);
    }

    /**
     * Open incidents + active emergencies as a unified, severity-ordered alert
     * feed (Core's `get_incidents` tool calls this as `get_alerts`).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getAlerts(string $organizationId, array $args): array
    {
        $limit = $this->limit($args['limit'] ?? null, default: 20);
        $severity = is_string($args['severity'] ?? null) ? $args['severity'] : null;

        $incidents = Incident::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', [IncidentStatus::Resolved->value, IncidentStatus::Closed->value])
            ->when($severity !== null, fn ($q) => $q->where('severity', $severity))
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get(['id', 'title', 'severity', 'status', 'opened_at']);

        $emergencies = Emergency::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [EmergencyStatus::Triggered->value, EmergencyStatus::Acknowledged->value])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'status', 'created_at']);

        $alerts = $incidents->map(fn (Incident $i): array => [
            'id' => $i->getKey(),
            'kind' => 'incident',
            'title' => $i->title,
            'severity' => $i->severity?->value,
            'status' => $i->status?->value,
            'at' => optional($i->opened_at)->toIso8601String(),
        ])->all();

        foreach ($emergencies as $e) {
            $alerts[] = [
                'id' => $e->getKey(),
                'kind' => 'emergency',
                'title' => 'Emergency',
                'severity' => 'critical',
                'status' => $e->status?->value,
                'at' => optional($e->created_at)->toIso8601String(),
            ];
        }

        return ['alerts' => array_slice($alerts, 0, $limit), 'count' => count($alerts)];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listIncidents(string $organizationId, array $args): array
    {
        $limit = $this->limit($args['limit'] ?? null, default: 25);
        $status = is_string($args['status'] ?? null) ? $args['status'] : null;

        $incidents = Incident::query()
            ->where('organization_id', $organizationId)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get(['id', 'title', 'severity', 'status', 'opened_at'])
            ->map(fn (Incident $i): array => [
                'id' => $i->getKey(),
                'title' => $i->title,
                'severity' => $i->severity?->value,
                'status' => $i->status?->value,
                'opened_at' => optional($i->opened_at)->toIso8601String(),
            ])->all();

        return ['incidents' => $incidents, 'count' => count($incidents)];
    }

    /**
     * Live situational snapshot for the org (mirrors /core/command-center but
     * service-scoped by orgId rather than the SuperAdmin user path).
     *
     * @return array<string, mixed>
     */
    private function commandOverview(string $organizationId): array
    {
        $bySeverity = [];
        foreach (IncidentSeverity::values() as $severity) {
            $bySeverity[$severity] = Incident::query()
                ->where('organization_id', $organizationId)
                ->whereNotIn('status', [IncidentStatus::Resolved->value, IncidentStatus::Closed->value])
                ->where('severity', $severity)
                ->count();
        }

        return [
            'open_incidents_by_severity' => $bySeverity,
            'open_incidents_total' => array_sum($bySeverity),
            'active_emergencies' => Emergency::query()
                ->where('organization_id', $organizationId)
                ->whereIn('status', [EmergencyStatus::Triggered->value, EmergencyStatus::Acknowledged->value])
                ->count(),
            'on_duty_responders' => Responder::query()
                ->where('organization_id', $organizationId)
                ->where('on_duty', true)
                ->count(),
        ];
    }

    /** A valid empty shape per tool, so Core never receives a confusing null. */
    private function emptyFor(string $tool): array
    {
        return match ($tool) {
            'get_alerts' => ['alerts' => [], 'count' => 0],
            'list_incidents' => ['incidents' => [], 'count' => 0],
            'command_overview' => [
                'open_incidents_by_severity' => [],
                'open_incidents_total' => 0,
                'active_emergencies' => 0,
                'on_duty_responders' => 0,
            ],
            default => [],
        };
    }

    private function limit(mixed $value, int $default): int
    {
        $n = is_numeric($value) ? (int) $value : $default;

        return max(1, min($n, 100));
    }

    /** Resolve the posted org identifier (UUID or slug) to an org id, or null. */
    private function resolveOrganizationId(string $org): ?string
    {
        if ($org === '') {
            return null;
        }

        $organization = Organization::query()
            ->when(
                Str::isUuid($org),
                fn ($query) => $query->where('id', $org),
                fn ($query) => $query->where('slug', $org),
            )
            ->first();

        return $organization?->getKey() !== null ? (string) $organization->getKey() : null;
    }
}
