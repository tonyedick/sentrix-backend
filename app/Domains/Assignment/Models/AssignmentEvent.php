<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, assignment-scoped operational timeline (distinct from the
 * cross-cutting audit trail). One row per meaningful step — offered, accepted,
 * rejected, timed-out, reassigned, escalated, cancelled — for the incident
 * console. Never edited or deleted.
 */
final class AssignmentEvent extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'assignment_id',
        'organization_id',
        'type',
        'actor_id',
        'assignment_responder_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
