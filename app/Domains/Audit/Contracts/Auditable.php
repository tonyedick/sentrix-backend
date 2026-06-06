<?php

declare(strict_types=1);

namespace App\Domains\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by domain events that should leave an audit-trail entry.
 *
 * The Audit domain listens for every dispatched event and records one row per
 * event implementing this contract — so domains opt into auditing without the
 * Audit domain depending on them, and without scattering audit calls through
 * services.
 */
interface Auditable
{
    /**
     * Dotted action key, e.g. "emergency.triggered".
     */
    public function auditAction(): string;

    /**
     * The tenant this event belongs to, or null for platform-level events.
     */
    public function auditOrganizationId(): ?string;

    /**
     * The acting user's id, or null for system/automated actions.
     */
    public function auditActorId(): ?string;

    /**
     * The model the action concerns (recorded polymorphically), or null.
     */
    public function auditSubject(): ?Model;

    /**
     * Extra structured context stored as JSONB.
     *
     * @return array<string, mixed>
     */
    public function auditMetadata(): array;
}
