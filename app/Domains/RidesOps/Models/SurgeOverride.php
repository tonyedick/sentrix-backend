<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual surge override pinned by Rides Ops. The CURRENT manual surge is the
 * latest `active` row; releasing the surge sets active=false (it is never
 * deleted, so the override history is preserved). Append-only: created_at only.
 *
 * PLATFORM-scoped: network-wide / cross-tenant, no organization_id.
 */
final class SurgeOverride extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'zone',
        'multiplier',
        'active',
        'set_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
