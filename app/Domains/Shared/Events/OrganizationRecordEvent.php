<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events;

use App\Domains\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for operational events about a single organization-scoped record
 * (a Trip, Emergency, Incident, …). It wires up BOTH cross-cutting behaviours
 * a safety-critical action needs:
 *
 *   - realtime: broadcasts to the record's organization channel (queued,
 *     after-commit), via {@see OrganizationBroadcastEvent}; and
 *   - audit: records an immutable trail entry (synchronous, in-transaction),
 *     via {@see Auditable}.
 *
 * Concrete events only declare their dotted {@see action()} (e.g. "trip.started").
 * The dispatching service passes the record, the acting user id, and a small
 * context payload that is used verbatim for both the broadcast body and the
 * audit metadata.
 */
abstract class OrganizationRecordEvent extends OrganizationBroadcastEvent implements Auditable
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Model $record,
        public readonly ?string $actorId = null,
        public readonly array $context = [],
    ) {}

    /**
     * Dotted action key shared by the broadcast name and the audit action,
     * e.g. "emergency.triggered".
     */
    abstract public function action(): string;

    public function organizationId(): string
    {
        return (string) $this->record->getAttribute('organization_id');
    }

    public function broadcastAs(): string
    {
        return $this->action();
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'id' => $this->record->getKey(),
            'type' => class_basename($this->record),
        ], $this->context);
    }

    public function auditAction(): string
    {
        return $this->action();
    }

    public function auditOrganizationId(): ?string
    {
        return $this->organizationId();
    }

    public function auditActorId(): ?string
    {
        return $this->actorId ?? auth()->id();
    }

    public function auditSubject(): ?Model
    {
        return $this->record;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditMetadata(): array
    {
        return $this->context;
    }
}
