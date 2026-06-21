<?php

declare(strict_types=1);

namespace App\Domains\Command\Models;

use App\Domains\Command\Support\Enums\CommandTier;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in an agency's 4-tier command hierarchy. parent_id builds the tree;
 * lat/lng (optional) let GPS-bearing alerts route to the nearest command.
 *
 * PLATFORM-scoped: maps to exactly one agency, no organization_id.
 */
final class Command extends Model
{
    use HasUuid;

    protected $fillable = [
        'agency_id',
        'parent_id',
        'tier',
        'name',
        'area',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'tier' => CommandTier::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Command, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
