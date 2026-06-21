<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\RecordRecentSearchRequest;
use App\Domains\Identity\Http\Resources\RecentSearchResource;
use App\Domains\Identity\Models\RecentSearch;
use App\Domains\Identity\Services\RecentSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user's recent destination searches, surfaced on the trip-planning screen.
 */
final class RecentSearchController extends Controller
{
    public function __construct(private readonly RecentSearchService $searches)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $searches = RecentSearch::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('searched_at')
            ->get();

        return RecentSearchResource::collection($searches);
    }

    public function store(RecordRecentSearchRequest $request): RecentSearchResource
    {
        $search = $this->searches->record($request->user(), $request->validated());

        return RecentSearchResource::make($search);
    }

    /**
     * Clear the entire recent-search history for the user.
     */
    public function destroy(Request $request): Response
    {
        RecentSearch::query()
            ->where('user_id', $request->user()->getKey())
            ->delete();

        return response()->noContent();
    }
}
