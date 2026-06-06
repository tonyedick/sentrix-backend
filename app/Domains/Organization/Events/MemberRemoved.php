<?php

declare(strict_types=1);

namespace App\Domains\Organization\Events;

use App\Domains\Audit\Contracts\Auditable;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MemberRemoved implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $user,
    ) {}

    public function auditAction(): string
    {
        return 'member.removed';
    }

    public function auditOrganizationId(): ?string
    {
        return $this->organization->getKey();
    }

    public function auditActorId(): ?string
    {
        return auth()->id();
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
        return ['removed_user_id' => $this->user->getKey()];
    }
}
