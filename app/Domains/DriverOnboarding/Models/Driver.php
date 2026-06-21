<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Models;

use App\Domains\DriverOnboarding\Support\Enums\DriverAvailability;
use App\Domains\DriverOnboarding\Support\Enums\DriverStage;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A driver onboarding profile. User-scoped: belongs 1:1 to the authenticated
 * user (one Driver per user). The stage machine is the heart of onboarding:
 * documents_review -> documents_approved -> inspection_booked -> active.
 */
final class Driver extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'stage',
        'availability',
        'reviewer_id',
        'review_note',
        'fleet_safety_score',
        'trips_count',
        'rating_avg',
        'vehicle_make',
        'vehicle_model',
        'vehicle_plate',
        'vehicle_color',
        'installed_hardware',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => DriverStage::class,
            'availability' => DriverAvailability::class,
            'fleet_safety_score' => 'integer',
            'trips_count' => 'integer',
            'rating_avg' => 'decimal:2',
            'installed_hardware' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DriverDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /**
     * @return HasMany<Inspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
