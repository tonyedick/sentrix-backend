<?php

declare(strict_types=1);

namespace App\Domains\Cad\Models;

use App\Domains\Cad\Support\Enums\UnitKind;
use App\Domains\Cad\Support\Enums\UnitStatus;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A first-class field unit with live status + location (AVL), belonging to a
 * command and (denormalized) its agency.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class Unit extends Model
{
    use HasUuid;

    protected $fillable = [
        'command_id',
        'agency_id',
        'call_sign',
        'kind',
        'capabilities',
        'crew',
        'lat',
        'lng',
        'area',
        'status',
        'assigned_incident_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => UnitKind::class,
            'status' => UnitStatus::class,
            'capabilities' => 'array',
            'crew' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<UnitDispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(UnitDispatch::class);
    }
}
