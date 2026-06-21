<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\VerificationCode;
use App\Domains\Identity\Notifications\VerificationCodeNotification;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and verifies short-lived OTP codes for consumer email/phone
 * verification. Codes are stored hashed; one live code per user+channel;
 * attempts are capped; success marks the channel verified.
 */
final readonly class OtpService
{
    public function __construct(private DatabaseManager $db) {}

    public function issue(User $user, string $channel): void
    {
        $length = (int) config('sentrix.auth.otp.length', 6);
        $ttl = (int) config('sentrix.auth.otp.ttl_seconds', 180);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $this->db->transaction(function () use ($user, $channel, $code, $ttl): void {
            // Invalidate any prior live code for this channel.
            VerificationCode::query()
                ->where('user_id', $user->getKey())
                ->where('channel', $channel)
                ->whereNull('consumed_at')
                ->delete();

            VerificationCode::create([
                'user_id' => $user->getKey(),
                'channel' => $channel,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds($ttl),
            ]);
        });

        $user->notify(new VerificationCodeNotification($code, $channel));
    }

    /**
     * Verify a submitted code. Returns true and marks the channel verified on
     * success; false on missing/expired/exhausted/incorrect codes.
     */
    public function verify(User $user, string $channel, string $code): bool
    {
        /** @var VerificationCode|null $record */
        $record = VerificationCode::query()
            ->where('user_id', $user->getKey())
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if ($record === null || $record->expires_at->isPast()) {
            return false;
        }

        $maxAttempts = (int) config('sentrix.auth.otp.max_attempts', 5);
        if ($record->attempts >= $maxAttempts) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        $user->forceFill([
            $channel === 'phone' ? 'phone_verified_at' : 'email_verified_at' => now(),
        ])->save();

        return true;
    }
}
