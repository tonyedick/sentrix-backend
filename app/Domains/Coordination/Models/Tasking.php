<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Models;

use App\Domains\Coordination\Support\Enums\TaskingKind;
use App\Domains\Coordination\Support\Enums\TaskingStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * An HQ duty tasking routed to an assignee: SENT -> ACKNOWLEDGED -> RESOLVED,
 * sender-stamped. Mirrors the SentrixGoBackend command router's tasking machine.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class Tasking extends Model
{
    use HasUuid;

    protected $fillable = [
        'kind',
        'ref',
        'title',
        'assignee',
        'status',
        'created_by',
        'acknowledged_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TaskingKind::class,
            'status' => TaskingStatus::class,
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
