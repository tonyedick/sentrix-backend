<?php

declare(strict_types=1);

namespace App\Domains\Responder\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\DTOs\RegisterResponderData;
use App\Domains\Responder\Events\ResponderRegistered;
use App\Domains\Responder\Events\ResponderStatusChanged;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the responder profile and its status lifecycle. Status changes are
 * validated against the ResponderStatus transition graph, run under a row lock
 * inside a transaction, and emit a broadcast + audited event — the same pattern
 * the Incident workflow uses.
 */
final readonly class ResponderService
{
    public function register(Organization $organization, User $user, RegisterResponderData $data): Responder
    {
        return DB::transaction(function () use ($organization, $user, $data): Responder {
            $responder = Responder::create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'status' => ResponderStatus::OffDuty,
                'on_duty' => false,
                'metadata' => $data->metadata,
            ]);

            event(new ResponderRegistered($responder, $user->getKey(), [
                'status' => $responder->status->value,
                'user_id' => $responder->user_id,
            ]));

            return $responder;
        });
    }

    public function goOnDuty(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::Available, $actor);
    }

    public function goOffDuty(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::OffDuty, $actor);
    }

    public function markUnavailable(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::Unavailable, $actor);
    }

    public function markAvailable(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::Available, $actor);
    }

    public function suspend(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::Suspended, $actor);
    }

    public function reinstate(Responder $responder, ?User $actor = null): Responder
    {
        return $this->transition($responder, ResponderStatus::OffDuty, $actor);
    }

    /**
     * Validate the status transition against the graph, apply it (keeping the
     * on_duty flag in sync), and emit ResponderStatusChanged. Lock + re-read so
     * the guard check and write are atomic against a concurrent transition. A
     * null actor denotes a system-driven change (e.g. the duty sweep).
     */
    public function transition(Responder $responder, ResponderStatus $target, ?User $actor = null): Responder
    {
        return DB::transaction(function () use ($responder, $target, $actor): Responder {
            $locked = Responder::query()->whereKey($responder->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $target) {
                return $locked;
            }

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => ["A responder cannot move from {$from->value} to {$target->value}."],
                ]);
            }

            $locked->update([
                'status' => $target,
                'on_duty' => $target->isOnDuty(),
            ]);

            event(new ResponderStatusChanged($locked, $actor?->getKey(), [
                'from' => $from->value,
                'to' => $target->value,
            ]));

            return $locked;
        });
    }
}
