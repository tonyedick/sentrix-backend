<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved place (Home / Work / custom) for quick trip planning.
 */
final class SavedLocation extends Model
{
    use HasUuid;

    protected $fillable = ['user_id', 'label', 'kind', 'address', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
