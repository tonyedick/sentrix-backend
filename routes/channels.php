<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 | Realtime channel authorization.
 |
 | Channels are private and organization-scoped per the event-system
 | conventions. User identifiers are UUIDs, so comparisons MUST be string
 | comparisons — casting a UUID to int truncates it to a partial/zero value
 | and would authorize the wrong user onto a private channel.
 */

// A user's personal notification channel.
Broadcast::channel('App.Models.User.{id}', static function (User $user, string $id): bool {
    return $user->getKey() === $id;
});

// Organization-scoped channel: only members of the organization may subscribe.
Broadcast::channel('organizations.{organizationId}', static function (User $user, string $organizationId): bool {
    return $user->belongsToOrganization($organizationId);
});
