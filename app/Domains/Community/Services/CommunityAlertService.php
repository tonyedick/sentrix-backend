<?php

declare(strict_types=1);

namespace App\Domains\Community\Services;

use App\Domains\Community\Models\CommunityAlert;
use App\Domains\Community\Support\Enums\AlertSource;
use App\Domains\Community\Support\Enums\AlertStatus;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Support\Enums\PlaceCategory;
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
     * Trust-weighted verification. A `verify` vote (it's still happening) adds
     * the voter's weight to the alert's confidence; a `dispute` (inaccurate)
     * subtracts it. A KYC-verified member's vote counts double. One vote per
     * user per alert (re-votes update in place, so a reporter's confirmation
     * counts once). Crossing the confidence thresholds flips the status:
     *   confidence >= confirm_threshold  -> active (verified)
     *   confidence <= dispute_threshold  -> resolved (dropped from the feed)
     * Trusted (official/ai) alerts stay verified and are never auto-resolved by
     * the crowd.
     */
    public function castTrustVote(
        CommunityAlert $alert,
        User $user,
        bool $confirm,
        ?string $impact = null,
        ?string $comment = null,
    ): CommunityAlert {
        $confirmThreshold = (int) config('sentrix.community.confirm_threshold', 3);
        $disputeThreshold = (int) config('sentrix.community.dispute_threshold', -2);

        return $this->db->transaction(function () use ($alert, $user, $confirm, $impact, $comment, $confirmThreshold, $disputeThreshold): CommunityAlert {
            $locked = CommunityAlert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();

            $weight = $this->trustWeight($user);

            // One vote per user (kind verify|dispute), updated in place on re-vote.
            $locked->confirmations()->updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'kind' => $confirm ? 'confirm' : 'dismiss',
                    'still_active' => $confirm,
                    'impact' => $impact,
                    'comment' => $comment,
                ],
            );

            // Recompute tallies + a trust-weighted confidence from the votes.
            $verifierIds = $locked->confirmations()->where('kind', 'confirm')->pluck('user_id')->all();
            $disputerIds = $locked->confirmations()->where('kind', 'dismiss')->pluck('user_id')->all();

            $locked->confirmations_count = count($verifierIds);
            $locked->dismissals_count = count($disputerIds);
            $locked->confidence = $this->weightedTally($verifierIds) - $this->weightedTally($disputerIds);

            if (! $locked->source->isTrusted()) {
                if ($locked->confidence <= $disputeThreshold && $locked->status !== AlertStatus::Resolved) {
                    $locked->status = AlertStatus::Resolved;
                } elseif ($locked->confidence >= $confirmThreshold && $locked->status === AlertStatus::Unverified) {
                    $locked->status = AlertStatus::Active;
                }
            }

            $locked->save();

            return $locked->refresh();
        });
    }

    /**
     * A citizen marks a COMMUNITY alert no-longer-active — it resolves and leaves
     * the feed. Official/AI alerts are managed by staff and are not resolvable by
     * citizens (the controller enforces that distinction).
     */
    public function resolve(CommunityAlert $alert): CommunityAlert
    {
        return $this->db->transaction(function () use ($alert): CommunityAlert {
            $locked = CommunityAlert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();
            $locked->status = AlertStatus::Resolved;
            $locked->save();

            return $locked->refresh();
        });
    }

    /**
     * Staff/Core publish a trusted alert (source=official|ai). Always created
     * verified (status=active) — no community confirmation required. SuperAdmin
     * gating is enforced at the controller.
     *
     * @param  array{source:string,category:string,title:string,note?:?string,impact?:?string,lat:float|int|string,lng:float|int|string}  $data
     */
    public function publish(User $publisher, array $data): CommunityAlert
    {
        $ttl = (int) config('sentrix.community.official_ttl_seconds', 86400);

        return CommunityAlert::create([
            'reporter_id' => $publisher->getKey(),
            'category' => $data['category'],
            'title' => $data['title'],
            'note' => $data['note'] ?? null,
            'impact' => $data['impact'] ?? 'high',
            'status' => AlertStatus::Active->value,
            'source' => $data['source'],
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
            'expires_at' => now()->addSeconds($ttl),
        ]);
    }

    /**
     * Verified safe locations (police, hospital, fire, safe_haven) near a point,
     * nearest first. Thin-wraps the Places POI directory, filtered to the safe
     * categories, reusing its PostGIS proximity query.
     */
    public function safePlaces(float $lat, float $lng, int $radiusMeters, int $perPage): LengthAwarePaginator
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return Place::query()
            ->selectRaw("places.*, ST_Distance(location, {$point}) AS distance_m", [$lng, $lat])
            ->whereIn('category', self::safeCategories())
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$lng, $lat, $radiusMeters])
            ->orderBy('distance_m')
            ->paginate($perPage);
    }

    /**
     * The Places categories that count as verified safe locations.
     *
     * @return list<string>
     */
    public static function safeCategories(): array
    {
        return [
            PlaceCategory::Police->value,
            PlaceCategory::Hospital->value,
            PlaceCategory::FireService->value,
        ];
    }

    /**
     * A KYC-verified member's vote counts double (trust weighting).
     */
    private function trustWeight(User $user): int
    {
        return $user->email_verified_at !== null ? 2 : 1;
    }

    /**
     * Sum the trust weights of a set of voter ids.
     *
     * @param  list<mixed>  $voterIds
     */
    private function weightedTally(array $voterIds): int
    {
        if ($voterIds === []) {
            return 0;
        }

        return (int) User::query()
            ->whereIn('id', $voterIds)
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NOT NULL THEN 2 ELSE 1 END) AS total')
            ->value('total');
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
