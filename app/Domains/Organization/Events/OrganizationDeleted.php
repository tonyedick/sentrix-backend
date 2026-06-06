<?php

declare(strict_types=1);

namespace App\Domains\Organization\Events;

use App\Domains\Audit\Contracts\Auditable;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An organization was soft-deleted. Audited only (not broadcast — its realtime
 * channel is being torn down).
 */
final class OrganizationDeleted implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Organization $organization,
    ) {}

    public function auditAction(): string
    {
        return 'organization.deleted';
    }

    public function auditOrganizationId(): ?string
    {
        return $this->organization->getKey();
    }

    public function auditActorId(): ?string
    {
        return auth()->id() ?? $this->organization->owner_id;
    }

    public function auditSubject(): ?Model
    {
        return $this->organization;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditMetadata(): array
    {
        return [
            'name' => $this->organization->name,
            'slug' => $this->organization->slug,
        ];
    }
}
