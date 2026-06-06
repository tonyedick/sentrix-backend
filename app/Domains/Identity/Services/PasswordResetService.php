<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Wraps Laravel's password broker so controllers stay thin and never touch the
 * broker directly. Returns the broker status string; the caller maps it to an
 * HTTP response.
 */
final readonly class PasswordResetService
{
    public function __construct(private PasswordBrokerFactory $brokers) {}

    /**
     * Email a password-reset link. Returns a broker status constant
     * (e.g. Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED).
     *
     * @param  array{email:string}  $credentials
     */
    public function sendResetLink(array $credentials): string
    {
        return $this->brokers->broker()->sendResetLink($credentials);
    }

    /**
     * Reset the password for the given credentials + token. Returns a broker
     * status constant (e.g. Password::PASSWORD_RESET, Password::INVALID_TOKEN).
     *
     * @param  array{email:string,password:string,token:string}  $credentials
     */
    public function reset(array $credentials): string
    {
        return $this->brokers->broker()->reset(
            $credentials,
            static function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all issued API tokens — a reset should log out every device.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );
    }
}
