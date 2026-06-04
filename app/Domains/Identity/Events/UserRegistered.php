<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a user account is created. Other domains (e.g. Organization)
 * subscribe to provision default resources without coupling to Auth.
 */
final class UserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
