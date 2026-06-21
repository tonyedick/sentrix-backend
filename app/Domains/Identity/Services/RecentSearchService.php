<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\RecentSearch;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Records recent destination searches, de-duplicated by label and capped to the
 * most recent N per user.
 */
final readonly class RecentSearchService
{
    private const KEEP = 10;

    public function __construct(private DatabaseManager $db) {}

    /**
     * @param  array{label:string,address?:?string,lat?:float|int|string|null,lng?:float|int|string|null}  $data
     */
    public function record(User $user, array $data): RecentSearch
    {
        return $this->db->transaction(function () use ($user, $data): RecentSearch {
            // De-dupe: a repeat search for the same label moves to the top.
            RecentSearch::query()->where('user_id', $user->getKey())->where('label', $data['label'])->delete();

            $search = RecentSearch::create([
                'user_id' => $user->getKey(),
                'label' => $data['label'],
                'address' => $data['address'] ?? null,
                'lat' => isset($data['lat']) ? (float) $data['lat'] : null,
                'lng' => isset($data['lng']) ? (float) $data['lng'] : null,
                'searched_at' => now(),
            ]);

            // Trim to the most recent N.
            $keepIds = RecentSearch::query()
                ->where('user_id', $user->getKey())
                ->latest('searched_at')
                ->limit(self::KEEP)
                ->pluck('id');
            RecentSearch::query()->where('user_id', $user->getKey())->whereNotIn('id', $keepIds)->delete();

            return $search;
        });
    }
}
