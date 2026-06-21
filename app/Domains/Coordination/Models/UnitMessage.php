<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Models;

use App\Domains\Cad\Models\Unit;
use App\Domains\Coordination\Support\Enums\MessageDirection;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single CAD-to-radio / MDT message in a unit's thread. dispatch_to_unit is an
 * outbound order; unit_to_dispatch is the field posting back. Mirrors Omni's
 * unitcomms.js (messages live against the unit).
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class UnitMessage extends Model
{
    use HasUuid;

    protected $fillable = [
        'unit_id',
        'command_incident_id',
        'direction',
        'body',
        'sender',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
