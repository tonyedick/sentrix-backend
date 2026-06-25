<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\DTOs\LoginData;
use App\Domains\Identity\DTOs\RegisterUserData;
use App\Domains\Identity\Events\UserRegistered;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Encapsulates all authentication side effects. Controllers stay thin and
 * never talk to the auth manager or token store directly.
 */
final readonly class AuthService
{
    public function __construct(
        private AuthManager $auth,
        private DatabaseManager $db,
    ) {}

    /**
     * Create a new user and emit the domain event.
     */
    public function register(RegisterUserData $data, bool $provisionDefaultOrganization = true): User
    {
        return $this->db->transaction(function () use ($data, $provisionDefaultOrganization): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password, // hashed by the model cast
            ]);

            event(new UserRegistered($user, $provisionDefaultOrganization));

            return $user;
        });
    }

    /**
     * Verify credentials and return the matching user, or throw a 422.
     *
     * Enumeration-resistant: when the account does not exist we still perform an
     * equivalent bcrypt operation so response timing does not reveal whether the
     * email is registered, and the error is identical to a wrong password.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(LoginData $data): User
    {
        /** @var User|null $user */
        $user = User::where('email', $data->email)->first();

        if ($user === null) {
            // Equalise timing with the password-check branch below.
            Hash::make($data->password);

            $this->failAuthentication();
        }

        if (! Hash::check($data->password, $user->password)) {
            $this->failAuthentication();
        }

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function failAuthentication(): never
    {
        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }

    /**
     * Issue a Sanctum personal access token for a native/mobile client.
     */
    public function issueToken(User $user, string $deviceName): NewAccessToken
    {
        // One active token per device: revoke stale tokens for the same name.
        $user->tokens()->where('name', $deviceName)->delete();

        return $user->createToken($deviceName);
    }

    /**
     * Issue a short-lived access token + a long-lived refresh token.
     *
     * The access token carries the usual `['*']` abilities but expires quickly;
     * the refresh token carries only `['refresh']` and is exchanged at
     * /auth/refresh for a fresh pair (the old refresh token is rotated out).
     *
     * @return array{access: NewAccessToken, refresh: NewAccessToken, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueTokenPair(User $user, string $deviceName): array
    {
        $refreshName = $deviceName.' (refresh)';

        // One active pair per device: revoke stale access + refresh tokens.
        $user->tokens()->whereIn('name', [$deviceName, $refreshName])->delete();

        $accessTtl = (int) config('sentrix.auth.access_ttl_minutes', 60);
        $refreshTtl = (int) config('sentrix.auth.refresh_ttl_days', 30);
        $expiresAt = now()->addMinutes($accessTtl);

        return [
            'access' => $user->createToken($deviceName, ['*'], $expiresAt),
            'refresh' => $user->createToken($refreshName, ['refresh'], now()->addDays($refreshTtl)),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Stateful SPA login (Inertia web).
     */
    public function loginStateful(User $user, bool $remember = false): void
    {
        $this->auth->guard('web')->login($user, $remember);
    }

    /**
     * Revoke the token used for the current request (mobile logout).
     */
    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }
    }

    /**
     * Rotate a signed-in user's password (re-auth with the current one).
     *
     * Token policy: unlike a password *reset* (which revokes every token, since
     * the reset is initiated out-of-band and we cannot trust any session),
     * a *self-service change* is initiated from an already-authenticated client,
     * so we keep the CURRENT request's token alive and revoke only the user's
     * OTHER tokens — the active device stays signed in while every other device
     * is forced to re-authenticate.
     *
     * @throws ValidationException when the supplied current password is wrong.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password is incorrect.')],
            ]);
        }

        $this->db->transaction(function () use ($user, $newPassword): void {
            // The 'hashed' cast on the User model hashes this on save.
            $user->forceFill(['password' => $newPassword])->save();

            $current = $user->currentAccessToken();
            $currentId = ($current !== null && method_exists($current, 'getKey')) ? $current->getKey() : null;

            // Revoke every OTHER personal access token; keep the current one.
            $user->tokens()
                ->when($currentId !== null, fn ($q) => $q->whereKeyNot($currentId))
                ->delete();
        });
    }

    /**
     * NDPR/GDPR right-to-erasure: permanently delete the caller's account.
     *
     * Org-owner guard: a consumer who OWNS one or more organizations cannot be
     * erased here, because organizations.owner_id cascades on delete — purging
     * the user would silently destroy a whole tenant (and every record under
     * it). We refuse with a 422 and instruct them to transfer ownership first.
     *
     * For an ordinary consumer (no owned org) we revoke every token and
     * forceDelete() the user inside a transaction. forceDelete bypasses the
     * SoftDeletes trait so the real DB row is removed, which fires the
     * cascadeOnDelete foreign keys on the user's consumer rows (safety_contacts,
     * saved_locations, recent_searches, social_accounts, ...).
     *
     * @throws ValidationException when the user still owns an organization.
     */
    public function deleteAccount(User $user): void
    {
        $ownedOrganizations = Organization::query()
            ->where('owner_id', $user->getKey())
            ->count();

        if ($ownedOrganizations > 0) {
            throw ValidationException::withMessages([
                'account' => [__('You own an organization. Transfer ownership before deleting your account.')],
            ]);
        }

        $this->db->transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->forceDelete();
        });
    }
}
