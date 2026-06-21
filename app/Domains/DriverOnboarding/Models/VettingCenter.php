<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical Sentrix Vetting Center where drivers attend an in-person vehicle
 * inspection + hardware install.
 */
final class VettingCenter extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'address',
        'lat',
        'lng',
        'slots',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'slots' => 'array',
        ];
    }

    /**
     * @return HasMany<Inspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
