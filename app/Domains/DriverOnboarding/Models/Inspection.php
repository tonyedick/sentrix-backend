<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Models;

use App\Domains\DriverOnboarding\Support\Enums\InspectionStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booked in-person vehicle inspection. On pass, Sentrix security hardware is
 * installed and the driver is activated.
 */
final class Inspection extends Model
{
    use HasUuid;

    protected $fillable = [
        'driver_id',
        'vetting_center_id',
        'booked_slot',
        'status',
        'checklist',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'checklist' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<VettingCenter, $this>
     */
    public function vettingCenter(): BelongsTo
    {
        return $this->belongsTo(VettingCenter::class);
    }
}
