<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Models;

use App\Domains\DriverOnboarding\Support\Enums\DocumentStatus;
use App\Domains\DriverOnboarding\Support\Enums\DocumentType;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded driver document awaiting (or having received) staff review.
 */
final class DriverDocument extends Model
{
    use HasUuid;

    protected $fillable = [
        'driver_id',
        'type',
        'url',
        'status',
        'note',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
