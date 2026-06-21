<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Jobs;

use App\Domains\Assignment\Services\DispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Expires an assignment offer (line) that was never accepted within the window.
 * Dispatched with a delay when the offer is made; the expire op is a no-op if
 * the line was already actioned, so a late run is harmless and idempotent.
 */
final class ExpireAssignmentOffer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $assignmentResponderId) {}

    public function handle(DispatchService $dispatch): void
    {
        $dispatch->timeout($this->assignmentResponderId);
    }
}
