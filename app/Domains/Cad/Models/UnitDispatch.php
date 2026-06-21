<?php

declare(strict_types=1);

namespace App\Domains\Cad\Models;

use App\Domains\Command\Models\CommandIncident;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The assignment record created when a unit is dispatched to a command incident
 * (Omni's assignUnit "creates an assignment record"). cleared_at + outcome close
 * it out when the unit clears the call.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class UnitDispatch extends Model
{
    use HasUuid;

    protected $fillable = [
        'unit_id',
        'command_incident_id',
        'dispatched_by',
        'dispatched_at',
        'cleared_at',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<CommandIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CommandIncident::class, 'command_incident_id');
    }
}
