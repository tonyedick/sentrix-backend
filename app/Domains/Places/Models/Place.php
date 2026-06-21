<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use App\Domains\Places\Support\Enums\PlaceCategory;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * A point of interest in the safety directory (emergency service or safe place).
 */
final class Place extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'category',
        'lat',
        'lng',
        'rating',
        'reviews_count',
        'is_24_7',
        'opens_at',
        'closes_at',
        'phone',
        'address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'category' => PlaceCategory::class,
            'lat' => 'float',
            'lng' => 'float',
            'rating' => 'float',
            'reviews_count' => 'integer',
            'is_24_7' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
