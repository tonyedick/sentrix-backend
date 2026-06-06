<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Contracts\Auditable;
use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit-trail rows. Writes are synchronous (not queued) so that an entry
 * is persisted in the same transaction as the action it records — if the action
 * rolls back, so does its audit row, and a committed action is never missing
 * from the trail.
 */
final class AuditLogger
{
    /**
     * Record an audit entry from an Auditable event.
     */
    public function record(Auditable $event): AuditLog
    {
        $subject = $event->auditSubject();

        return $this->log(
            action: $event->auditAction(),
            organizationId: $event->auditOrganizationId(),
            actorId: $event->auditActorId(),
            subject: $subject,
            metadata: $event->auditMetadata(),
        );
    }

    /**
     * Low-level audit write. Provenance (IP / user agent) is captured from the
     * active HTTP request when one exists.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?string $organizationId = null,
        ?string $actorId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditLog {
        $request = app()->runningInConsole() ? null : (app()->bound('request') ? app('request') : null);

        return AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $actorId,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
        ]);
    }
}
