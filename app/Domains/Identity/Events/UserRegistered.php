<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a user account is created. Other domains (e.g. Organization)
 * subscribe to provision default resources without coupling to Auth.
 *
 * `provisionDefaultOrganization` separates operational/dashboard signups (which
 * receive a personal workspace) from consumer/mobile signups (which are
 * user-scoped and served by the monitoring org — see ADR-0001 — so they get no
 * personal org).
 */
final class UserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly bool $provisionDefaultOrganization = true,
    ) {}
}
