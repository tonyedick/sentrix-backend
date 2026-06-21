<?php

declare(strict_types=1);

namespace App\Domains\Notification\Services;

use App\Domains\Notification\Models\NotificationPolicy;

/**
 * Resolves the effective notification policy for an organization: the saved
 * notification_policies row, or a transient instance backed by the
 * sentrix.notifications.channels config defaults when none exists.
 */
final readonly class NotificationPolicyResolver
{
    public function for(string $organizationId): NotificationPolicy
    {
        return NotificationPolicy::query()
            ->where('organization_id', $organizationId)
            ->first()
            ?? new NotificationPolicy([
                'organization_id' => $organizationId,
                'channels' => (array) config('sentrix.notifications.channels', ['mail', 'database', 'broadcast']),
            ]);
    }
}
