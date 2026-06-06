<?php

declare(strict_types=1);

namespace App\Domains\Organization\Events;

use App\Domains\Audit\Contracts\Auditable;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user joins an organization (via creation, invite acceptance, or
 * direct add). Broadcast to the organization's private channel so live member
 * lists update without a refetch — this is the platform's first realtime signal
 * and the reference implementation other domains follow.
 *
 * Broadcasting is queued (ShouldBroadcast) and dispatched only after the
 * surrounding DB transaction commits, so subscribers never observe a member
 * that a rolled-back transaction never actually created.
 */
final class MemberJoined implements Auditable, ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Dispatch the queued broadcast only after the enclosing DB transaction
     * commits (membership writes happen inside a transaction).
     */
    public bool $afterCommit = true;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $user,
        public readonly string $role,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->organization->getKey()}"),
        ];
    }

    /**
     * Stable client-facing event name (decoupled from the PHP class name).
     */
    public function broadcastAs(): string
    {
        return 'member.joined';
    }

    /**
     * The wire payload. Kept deliberately small — clients refetch detail on
     * demand; the broadcast only signals "who joined, with what role".
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'organization_id' => $this->organization->getKey(),
            'user' => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
            ],
            'role' => $this->role,
        ];
    }

    public function auditAction(): string
    {
        return 'member.joined';
    }

    public function auditOrganizationId(): ?string
    {
        return $this->organization->getKey();
    }

    public function auditActorId(): ?string
    {
        // The actor is whoever is acting (an inviter/admin), falling back to the
        // joining user for self-service joins (e.g. accepting an invitation).
        return auth()->id() ?? $this->user->getKey();
    }

    public function auditSubject(): ?Model
    {
        return $this->user;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditMetadata(): array
    {
        return ['role' => $this->role];
    }
}
