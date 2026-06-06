<?php

declare(strict_types=1);

namespace App\Domains\Incident\Services;

use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\DTOs\OpenIncidentData;
use App\Domains\Incident\DTOs\UpdateIncidentData;
use App\Domains\Incident\Events\IncidentClosed;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Events\IncidentResolved;
use App\Domains\Incident\Events\IncidentStatusChanged;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the incident workflow. State changes are validated against the
 * IncidentStatus transition graph, run in a transaction, and emit a broadcast +
 * audited event.
 */
final readonly class IncidentService
{
    public function open(Organization $organization, OpenIncidentData $data, User $actor): Incident
    {
        return DB::transaction(function () use ($organization, $data, $actor): Incident {
            $incident = Incident::create([
                'organization_id' => $organization->getKey(),
                'emergency_id' => $data->emergencyId,
                'opened_by' => $actor->getKey(),
                'assigned_to' => $data->assignedTo,
                'status' => IncidentStatus::Open,
                'severity' => $data->severity,
                'title' => $data->title,
                'summary' => $data->summary,
                'opened_at' => now(),
            ]);

            event(new IncidentOpened($incident, $actor->getKey(), [
                'status' => $incident->status->value,
                'severity' => $incident->severity->value,
                'emergency_id' => $incident->emergency_id,
            ]));

            return $incident;
        });
    }

    /**
     * Auto-open an incident from an emergency (escalation chain). Idempotent: a
     * no-op if an incident is already linked to the emergency, so a retried
     * listener never creates duplicates. Attributed to the emergency's subject;
     * not actor-driven.
     */
    public function openFromEmergency(Emergency $emergency): ?Incident
    {
        return DB::transaction(function () use ($emergency): ?Incident {
            $alreadyLinked = Incident::query()
                ->where('emergency_id', $emergency->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyLinked) {
                return null;
            }

            $incident = Incident::create([
                'organization_id' => $emergency->organization_id,
                'emergency_id' => $emergency->getKey(),
                'opened_by' => $emergency->user_id,
                'status' => IncidentStatus::Open,
                'severity' => IncidentSeverity::Critical,
                'title' => 'Critical emergency escalation',
                'summary' => $emergency->message,
                'opened_at' => now(),
                'metadata' => ['source' => 'emergency.critical'],
            ]);

            event(new IncidentOpened($incident, null, [
                'status' => $incident->status->value,
                'severity' => $incident->severity->value,
                'emergency_id' => $incident->emergency_id,
                'source' => 'emergency.critical',
            ]));

            return $incident;
        });
    }

    /**
     * Patch detail fields (title, summary, severity, assignee) without changing
     * workflow state.
     */
    public function updateDetails(Incident $incident, UpdateIncidentData $data): Incident
    {
        $attributes = $data->toAttributes();

        if ($attributes !== []) {
            $incident->update($attributes);
        }

        return $incident->refresh();
    }

    /**
     * Move an incident into the investigating state (from open, or by reopening
     * a resolved incident).
     */
    public function startInvestigation(Incident $incident, User $actor): Incident
    {
        return $this->transition(
            $incident,
            IncidentStatus::Investigating,
            $actor,
            fn (Incident $i, string $from) => new IncidentStatusChanged($i, $actor->getKey(), [
                'from' => $from,
                'to' => $i->status->value,
            ]),
        );
    }

    public function escalate(Incident $incident, User $actor): Incident
    {
        return $this->transition(
            $incident,
            IncidentStatus::Escalated,
            $actor,
            fn (Incident $i, string $from) => new IncidentEscalated($i, $actor->getKey(), [
                'from' => $from,
                'to' => $i->status->value,
            ]),
            ['escalated_at' => now()],
        );
    }

    public function resolve(Incident $incident, User $actor): Incident
    {
        return $this->transition(
            $incident,
            IncidentStatus::Resolved,
            $actor,
            fn (Incident $i, string $from) => new IncidentResolved($i, $actor->getKey(), [
                'from' => $from,
                'to' => $i->status->value,
            ]),
            ['resolved_at' => now()],
        );
    }

    public function close(Incident $incident, User $actor): Incident
    {
        return $this->transition(
            $incident,
            IncidentStatus::Closed,
            $actor,
            fn (Incident $i, string $from) => new IncidentClosed($i, $actor->getKey(), [
                'from' => $from,
                'to' => $i->status->value,
            ]),
            ['closed_at' => now()],
        );
    }

    /**
     * Validate the transition against the status graph, apply it (plus any
     * milestone timestamps), and emit the supplied event.
     *
     * @param  callable(Incident, string): object  $makeEvent
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transition(
        Incident $incident,
        IncidentStatus $target,
        User $actor,
        callable $makeEvent,
        array $extraAttributes = [],
    ): Incident {
        return DB::transaction(function () use ($incident, $target, $makeEvent, $extraAttributes): Incident {
            // Lock and re-read so the transition check + write are atomic against
            // a concurrent transition on the same incident.
            $locked = Incident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => ["An incident cannot move from {$from->value} to {$target->value}."],
                ]);
            }

            $locked->update(array_merge(['status' => $target], $extraAttributes));

            event($makeEvent($locked, $from->value));

            return $locked;
        });
    }
}
