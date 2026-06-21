<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trusted contact a user wants alerted on SOS / overdue / emergency.
 */
final class SafetyContact extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'relationship',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
