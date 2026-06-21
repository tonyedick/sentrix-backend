<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Jobs;

use App\Domains\Assignment\Events\ResponderAssignmentRecommended;
use App\Domains\Assignment\Services\DispatchRecommender;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Produces an advisory responder shortlist for a newly opened incident or
 * triggered emergency and emits ResponderAssignmentRecommended for dispatchers.
 * Low-priority queue (never blocks operational flow). Never auto-dispatches.
 */
final class RecommendResponderAssignment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param  class-string<Incident|Emergency>  $targetType
     */
    public function __construct(
        public readonly string $targetType,
        public readonly string $targetId,
    ) {
        // Set via the trait method, not a typed property (redeclaring
        // Queueable::$queue with a type is an incompatible composition).
        $this->onQueue('ai');
    }

    public function handle(DispatchRecommender $recommender): void
    {
        /** @var Incident|Emergency|null $target */
        $target = $this->targetType::query()->find($this->targetId);

        if (! $target instanceof Model) {
            return;
        }

        $lat = $target->getAttribute('lat');
        $lng = $target->getAttribute('lng');

        $candidates = $recommender->recommend(
            (string) $target->getAttribute('organization_id'),
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
            (int) config('sentrix.responders.ai_dispatch_shortlist_size', 5),
        );

        event(new ResponderAssignmentRecommended($target, null, [
            'target_type' => class_basename($target),
            'candidates' => $candidates,
        ]));
    }
}
