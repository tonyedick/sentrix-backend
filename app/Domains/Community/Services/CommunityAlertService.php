<?php

declare(strict_types=1);

namespace App\Domains\Community\Services;

use App\Domains\Community\Models\CommunityAlert;
use App\Domains\Community\Support\Enums\AlertStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

/**
 * Crowdsourced community alerts: report, geo-feed, and crowd verification
 * (confirm / dismiss). User-scoped (ADR-0001). Geo queries use the PostGIS
 * `location` column (metres) — same pattern as Tracking/Responder proximity.
 */
final readonly class CommunityAlertService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @param  array{category:string,title:string,note?:?string,impact?:?string,lat:float|int|string,lng:float|int|string}  $data
     */
    public function report(User $reporter, array $data): CommunityAlert
    {
        $ttl = (int) config('sentrix.community.alert_ttl_seconds', 21600);

        return CommunityAlert::create([
            'reporter_id' => $reporter->getKey(),
            'category' => $data['category'],
            'title' => $data['title'],
            'note' => $data['note'] ?? null,
            'impact' => $data['impact'] ?? 'moderate',
            'status' => AlertStatus::Active->value,
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
            'expires_at' => now()->addSeconds($ttl),
        ]);
    }

    /**
     * Record a verification vote (one per user per alert; updated in place),
     * recompute tallies, and resolve the alert once enough users dismiss it.
     */
    public function recordVote(
        CommunityAlert $alert,
        User $user,
        string $kind,
        bool $stillActive = true,
        ?string $impact = null,
        ?string $comment = null,
    ): CommunityAlert {
        $threshold = (int) config('sentrix.community.dismiss_threshold', 3);

        return $this->db->transaction(function () use ($alert, $user, $kind, $stillActive, $impact, $comment, $threshold): CommunityAlert {
            $locked = CommunityAlert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();

            $locked->confirmations()->updateOrCreate(
                ['user_id' => $user->getKey()],
                ['kind' => $kind, 'still_active' => $stillActive, 'impact' => $impact, 'comment' => $comment],
            );

            $confirmations = $locked->confirmations()->where('kind', 'confirm')->count();
            $dismissals = $locked->confirmations()->where('kind', 'dismiss')->count();

            $locked->confirmations_count = $confirmations;
            $locked->dismissals_count = $dismissals;

            if ($dismissals >= $threshold && $locked->status === AlertStatus::Active) {
                $locked->status = AlertStatus::Resolved;
            }

            $locked->save();

            return $locked->refresh();
        });
    }

    /**
     * Count active, unexpired alerts within $radiusMeters of a point. Used by
     * routing to score corridor risk.
     */
    public function countActiveNear(float $lat, float $lng, int $radiusMeters): int
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return CommunityAlert::query()
            ->where('status', AlertStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$lng, $lat, $radiusMeters])
            ->count();
    }

    /**
     * Active, unexpired alerts within $radiusMeters of a point, nearest first,
     * each carrying a `distance_m` attribute.
     */
    public function nearby(float $lat, float $lng, int $radiusMeters, ?string $category, int $perPage): LengthAwarePaginator
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return CommunityAlert::query()
            ->selectRaw("community_alerts.*, ST_Distance(location, {$point}) AS distance_m", [$lng, $lat])
            ->where('status', AlertStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($category !== null, fn ($q) => $q->where('category', $category))
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$lng, $lat, $radiusMeters])
            ->orderBy('distance_m')
            ->paginate($perPage);
    }
}
