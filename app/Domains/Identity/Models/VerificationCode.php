<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short-lived, hashed OTP code for verifying a user's email or phone.
 * One live (unconsumed) code per user+channel; the OtpService manages issuance,
 * attempt counting, and consumption.
 */
final class VerificationCode extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'channel',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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
