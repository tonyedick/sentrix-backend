<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recent destination search for quick re-selection on the trip screen.
 */
final class RecentSearch extends Model
{
    use HasUuid;

    protected $fillable = ['user_id', 'label', 'address', 'lat', 'lng', 'searched_at'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'searched_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
