<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\DTOs\RegisterUserData;
use App\Domains\Auth\Events\UserRegistered;
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
    public function register(RegisterUserData $data): User
    {
        return $this->db->transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password, // hashed by the model cast
            ]);

            event(new UserRegistered($user));

            return $user;
        });
    }

    /**
     * Verify credentials and return the matching user, or throw a 422.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(LoginData $data): User
    {
        /** @var User|null $user */
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $user;
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
}
