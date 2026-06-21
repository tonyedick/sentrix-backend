<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\StoreSavedLocationRequest;
use App\Domains\Identity\Http\Resources\SavedLocationResource;
use App\Domains\Identity\Models\SavedLocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user's saved places (Home / Work / custom) for quick trip planning.
 */
final class SavedLocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locations = SavedLocation::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->get();

        return SavedLocationResource::collection($locations);
    }

    public function store(StoreSavedLocationRequest $request): SavedLocationResource
    {
        $location = SavedLocation::create([
            'user_id' => $request->user()->getKey(),
            ...$request->validated(),
        ]);

        return SavedLocationResource::make($location);
    }

    public function update(StoreSavedLocationRequest $request, SavedLocation $savedLocation): SavedLocationResource
    {
        $this->assertOwned($request, $savedLocation);
        $savedLocation->update($request->validated());

        return SavedLocationResource::make($savedLocation->refresh());
    }

    public function destroy(Request $request, SavedLocation $savedLocation): Response
    {
        $this->assertOwned($request, $savedLocation);
        $savedLocation->delete();

        return response()->noContent();
    }

    private function assertOwned(Request $request, SavedLocation $location): void
    {
        abort_if($location->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
