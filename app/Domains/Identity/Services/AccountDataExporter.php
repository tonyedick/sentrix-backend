<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Http\Resources\RecentSearchResource;
use App\Domains\Identity\Http\Resources\SafetyContactResource;
use App\Domains\Identity\Http\Resources\SavedLocationResource;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Models\RecentSearch;
use App\Domains\Identity\Models\SafetyContact;
use App\Domains\Identity\Models\SavedLocation;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Assembles the NDPR/GDPR right-of-access export for a consumer: everything the
 * Identity domain stores keyed to the user, projected through the same API
 * Resources the live endpoints use so the export and the live data stay
 * identical. Read-only. Mirrors the SentrixGo GET /auth/me/export contract,
 * extended with the saved-locations and recent-searches the Laravel domain owns.
 */
final readonly class AccountDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function export(User $user, Request $request): array
    {
        $contacts = SafetyContact::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        $savedLocations = SavedLocation::query()
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->get();

        $recentSearches = RecentSearch::query()
            ->where('user_id', $user->getKey())
            ->latest('searched_at')
            ->get();

        return [
            'exported_for' => $user->email,
            'exported_at' => now()->toIso8601String(),
            // resolve() (not toArray()) so Laravel strips any whenLoaded()
            // MissingValue — e.g. the nested organization is absent for a
            // consumer with no org, and toArray() would leave a MissingValue
            // that fatals on serialization.
            'profile' => UserResource::make($user)->resolve($request),
            'safety_contacts' => SafetyContactResource::collection($contacts)->toArray($request),
            'emergency_contacts' => SafetyContactResource::collection($contacts)->toArray($request),
            'saved_locations' => SavedLocationResource::collection($savedLocations)->toArray($request),
            'recent_searches' => RecentSearchResource::collection($recentSearches)->toArray($request),
        ];
    }
}
