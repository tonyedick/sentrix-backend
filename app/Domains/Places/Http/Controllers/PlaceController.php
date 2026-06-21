<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Controllers;

use App\Domains\Places\Http\Requests\NearbyPlacesRequest;
use App\Domains\Places\Http\Resources\PlaceResource;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Services\PlaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Safety POI directory (Emergency Points + Nearby Safe Places). Shared
 * reference data; any authenticated user can query nearby.
 */
final class PlaceController extends Controller
{
    public function __construct(private readonly PlaceService $places) {}

    public function index(NearbyPlacesRequest $request): AnonymousResourceCollection
    {
        $radius = (int) $request->integer('radius', (int) config('sentrix.places.default_radius_m', 5000));

        $places = $this->places->nearby(
            (float) $request->float('lat'),
            (float) $request->float('lng'),
            $radius,
            $request->input('category'),
            $request->boolean('open_now'),
            $this->perPage($request),
        );

        return PlaceResource::collection($places);
    }

    public function show(Place $place): PlaceResource
    {
        return PlaceResource::make($place);
    }
}
