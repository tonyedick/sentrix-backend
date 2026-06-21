<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Jobs\RecommendResponderAssignment;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * When an incident is opened or an emergency is triggered, queue an advisory
 * dispatch recommendation — only if AI dispatch is enabled. Light (just
 * dispatches the queued job) so it never blocks the operational flow.
 */
final class QueueDispatchRecommendation
{
    public function handle(OrganizationRecordEvent $event): void
    {
        if (! config('sentrix.responders.ai_dispatch_enabled', false)) {
            return;
        }

        $record = $event->record;

        if (! $record instanceof Incident && ! $record instanceof Emergency) {
            return;
        }

        RecommendResponderAssignment::dispatch($record::class, (string) $record->getKey());
    }
}
