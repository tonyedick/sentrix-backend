<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Services;

use App\Domains\Responder\Models\Responder;
use Illuminate\Support\Facades\DB;

/**
 * Produces a ranked shortlist of candidate responders for a target. Advisory
 * seam for AI-assisted dispatch: the default is a transparent heuristic
 * (proximity when coordinates are known, else presence recency); a model-backed
 * recommender can replace {@see rank()} without touching the event/job wiring.
 */
final readonly class DispatchRecommender
{
    /**
     * @param  list<string>  $excludeResponderIds  responders already tried on this assignment
     * @return list<array{responder_id: string, user_id: string, score: float, distance_meters: float|null, reasons: list<string>}>
     */
    public function recommend(string $organizationId, ?float $lat, ?float $lng, int $limit, array $excludeResponderIds = []): array
    {
        $hasCoordinates = $lat !== null && $lng !== null && DB::getDriverName() === 'pgsql';

        $query = Responder::query()
            ->where('organization_id', $organizationId)
            ->when($excludeResponderIds !== [], fn ($q) => $q->whereNotIn('id', $excludeResponderIds))
            ->assignable();

        if ($hasCoordinates) {
            $query->selectRaw(
                'responders.*, ST_Distance(last_location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_meters',
                [$lng, $lat],
            )
                ->whereNotNull('last_lat')
                ->orderByRaw('distance_meters ASC NULLS LAST');
        } else {
            $query->orderByDesc('last_seen_at');
        }

        return $this->rank($query->limit(max(1, $limit))->get()->all(), $hasCoordinates);
    }

    /**
     * @param  list<Responder>  $candidates
     * @return list<array{responder_id: string, user_id: string, score: float, distance_meters: float|null, reasons: list<string>}>
     */
    private function rank(array $candidates, bool $hasCoordinates): array
    {
        return array_map(static function (Responder $responder) use ($hasCoordinates): array {
            $distance = $hasCoordinates && isset($responder->distance_meters)
                ? (float) $responder->distance_meters
                : null;

            $reasons = ['available'];
            $score = 1.0;

            if ($distance !== null) {
                $reasons[] = 'nearest';
                $score = 1.0 / (1.0 + ($distance / 1000));
            }

            return [
                'responder_id' => (string) $responder->getKey(),
                'user_id' => (string) $responder->user_id,
                'score' => round($score, 4),
                'distance_meters' => $distance,
                'reasons' => $reasons,
            ];
        }, $candidates);
    }
}
