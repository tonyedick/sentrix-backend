<?php

declare(strict_types=1);

use App\Domains\Realtime\Support\RealtimeChannelPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 | Realtime channel authorization.
 |
 | Channels are organization-scoped and role/permission-scoped. All operational
 | channels delegate to RealtimeChannelPolicy, which enforces (1) cross-org
 | isolation via organization membership and (2) the channel's permission in that
 | org's team context. User identifiers are UUIDs, so comparisons MUST be string
 | comparisons.
 */

// A user's personal channel (assignment offers, personal notifications).
Broadcast::channel('App.Models.User.{id}', static function (User $user, string $id): bool {
    return $user->getKey() === $id;
});

// General organization channel (fallback for emergency/trip events): members only.
Broadcast::channel('organizations.{organizationId}', static function (User $user, string $organizationId): bool {
    return $user->isSuperAdmin() || $user->belongsToOrganization($organizationId);
});

// Coordination dashboard (assignments.view).
Broadcast::channel('organizations.{organizationId}.dashboard', static function (User $user, string $organizationId): bool {
    return app(RealtimeChannelPolicy::class)->dashboard($user, $organizationId);
});

// Incident monitoring (incidents.view).
Broadcast::channel('organizations.{organizationId}.incidents', static function (User $user, string $organizationId): bool {
    return app(RealtimeChannelPolicy::class)->incidents($user, $organizationId);
});

// Assignment / dispatch updates (assignments.view).
Broadcast::channel('organizations.{organizationId}.assignments', static function (User $user, string $organizationId): bool {
    return app(RealtimeChannelPolicy::class)->assignments($user, $organizationId);
});

// Presence roster: on-duty responders join as `responder`; responders.view holders
// join as `observer`. Returning an array joins the presence set.
Broadcast::channel('organizations.{organizationId}.responders', static function (User $user, string $organizationId): array|bool {
    return app(RealtimeChannelPolicy::class)->responderPresence($user, $organizationId);
});
