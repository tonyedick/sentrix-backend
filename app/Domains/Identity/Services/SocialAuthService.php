<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SocialVerifier;
use App\Domains\Identity\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves (or provisions) a Sentrix user from a verified social identity.
 * Matches first on the linked provider identity, then on email; new users are
 * created already email-verified (the provider attests the email) and, as
 * consumer signups, receive no personal organization (ADR-0001).
 */
final readonly class SocialAuthService
{
    public function __construct(
        private SocialVerifier $verifier,
        private DatabaseManager $db,
    ) {}

    public function authenticate(string $provider, string $idToken): User
    {
        $identity = $this->verifier->verify($provider, $idToken);

        return $this->db->transaction(function () use ($identity): User {
            $account = SocialAccount::query()
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->providerUserId)
                ->first();

            if ($account !== null) {
                return $account->user;
            }

            $user = User::query()->where('email', $identity->email)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $identity->name,
                    'email' => $identity->email,
                    'password' => Hash::make(Str::random(40)),
                ]);

                // email_verified_at is guarded (not fillable) — the provider has
                // already verified the address, so mark it verified explicitly.
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            SocialAccount::create([
                'user_id' => $user->getKey(),
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
            ]);

            return $user;
        });
    }
}
