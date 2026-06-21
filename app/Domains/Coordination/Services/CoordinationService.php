<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Services;

use App\Domains\Coordination\DTOs\RecordDutyData;
use App\Domains\Coordination\DTOs\RequestMutualAidData;
use App\Domains\Coordination\DTOs\RouteTaskingData;
use App\Domains\Coordination\DTOs\SendUnitMessageData;
use App\Domains\Coordination\Events\MutualAidRequested;
use App\Domains\Coordination\Events\TaskingRouted;
use App\Domains\Coordination\Events\UnitMessageSent;
use App\Domains\Coordination\Models\DutyEntry;
use App\Domains\Coordination\Models\MutualAidRequest;
use App\Domains\Coordination\Models\Tasking;
use App\Domains\Coordination\Models\UnitMessage;
use App\Domains\Coordination\Support\Enums\MessageDirection;
use App\Domains\Coordination\Support\Enums\MutualAidStatus;
use App\Domains\Coordination\Support\Enums\TaskingStatus;
use App\Domains\Cad\Models\Unit;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coordination writes across the four clusters: mutual aid, unit comms, taskings
 * and the duty book. Mirrors Omni's mutualaid.js / unitcomms.js and the
 * SentrixGoBackend command router (taskings / duty).
 *
 * Stateless final readonly service. Writes run in a transaction; status
 * transitions lock + re-read the row before mutating (concurrency-safe).
 */
final readonly class CoordinationService
{
    // ---- Mutual aid -------------------------------------------------------

    public function requestAid(RequestMutualAidData $data, ?string $actorId = null): MutualAidRequest
    {
        return DB::transaction(function () use ($data, $actorId): MutualAidRequest {
            $request = MutualAidRequest::create([
                'command_incident_id' => $data->commandIncidentId,
                'requesting_command_id' => $data->requestingCommandId,
                'responding_command_id' => $data->respondingCommandId,
                'status' => MutualAidStatus::Requested,
                'message' => $data->message,
                'requested_by' => $actorId,
            ]);

            event(new MutualAidRequested($request, $actorId));

            return $request;
        });
    }

    /**
     * Accept a mutual-aid request. Concurrency-safe: locks + re-reads, and only
     * transitions from `requested` (else 422). Stamps responded_at.
     */
    public function acceptAid(MutualAidRequest $request): MutualAidRequest
    {
        return $this->respondToAid($request, MutualAidStatus::Accepted);
    }

    /**
     * Decline a mutual-aid request (requested -> declined). Same interlock.
     */
    public function declineAid(MutualAidRequest $request): MutualAidRequest
    {
        return $this->respondToAid($request, MutualAidStatus::Declined);
    }

    private function respondToAid(MutualAidRequest $request, MutualAidStatus $target): MutualAidRequest
    {
        return DB::transaction(function () use ($request, $target): MutualAidRequest {
            /** @var MutualAidRequest $locked */
            $locked = MutualAidRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $locked->status === MutualAidStatus::Requested,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'mutual_aid_not_pending'
            );

            $locked->status = $target;
            $locked->responded_at = now();
            $locked->save();

            return $locked;
        });
    }

    // ---- Unit comms -------------------------------------------------------

    public function sendUnitMessage(Unit $unit, SendUnitMessageData $data, ?string $actorId = null): UnitMessage
    {
        return DB::transaction(function () use ($unit, $data, $actorId): UnitMessage {
            $message = UnitMessage::create([
                'unit_id' => $unit->getKey(),
                'command_incident_id' => $data->commandIncidentId,
                'direction' => $data->direction,
                'body' => $data->body,
                'sender' => $actorId,
            ]);

            event(new UnitMessageSent($message, $actorId));

            return $message;
        });
    }

    /**
     * Mark a unit's dispatch-visible (inbound) unread messages as read. Returns
     * the number marked. Idempotent.
     */
    public function markThreadRead(Unit $unit): int
    {
        return UnitMessage::query()
            ->where('unit_id', $unit->getKey())
            ->where('direction', MessageDirection::UnitToDispatch->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    // ---- Taskings ---------------------------------------------------------

    public function routeTasking(RouteTaskingData $data, ?string $actorId = null): Tasking
    {
        return DB::transaction(function () use ($data, $actorId): Tasking {
            $tasking = Tasking::create([
                'kind' => $data->kind,
                'ref' => $data->ref,
                'title' => $data->title,
                'assignee' => $data->assignee,
                'status' => TaskingStatus::Sent,
                'created_by' => $actorId,
            ]);

            event(new TaskingRouted($tasking, $actorId));

            return $tasking;
        });
    }

    /**
     * Acknowledge a tasking (sent -> acknowledged). Concurrency-safe; only from
     * `sent` (else 422).
     */
    public function acknowledgeTasking(Tasking $tasking): Tasking
    {
        return DB::transaction(function () use ($tasking): Tasking {
            /** @var Tasking $locked */
            $locked = Tasking::query()->whereKey($tasking->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->status === TaskingStatus::Sent,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'tasking_not_sent'
            );

            $locked->status = TaskingStatus::Acknowledged;
            $locked->acknowledged_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Resolve a tasking (sent|acknowledged -> resolved). Concurrency-safe;
     * resolving an already-resolved tasking is rejected (422).
     */
    public function resolveTasking(Tasking $tasking): Tasking
    {
        return DB::transaction(function () use ($tasking): Tasking {
            /** @var Tasking $locked */
            $locked = Tasking::query()->whereKey($tasking->getKey())->lockForUpdate()->firstOrFail();

            abort_if(
                $locked->status === TaskingStatus::Resolved,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'tasking_already_resolved'
            );

            $locked->status = TaskingStatus::Resolved;
            $locked->resolved_at = now();
            $locked->save();

            return $locked;
        });
    }

    // ---- Duty book --------------------------------------------------------

    public function recordDuty(RecordDutyData $data): DutyEntry
    {
        return DB::transaction(static function () use ($data): DutyEntry {
            return DutyEntry::create([
                'scope_type' => $data->scopeType,
                'scope_id' => $data->scopeId,
                'person_name' => $data->personName,
                'role' => $data->role,
                'action' => $data->action,
                'recorded_at' => now(),
            ]);
        });
    }
}
