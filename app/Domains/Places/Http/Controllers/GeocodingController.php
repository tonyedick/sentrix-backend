<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Controllers;

use App\Domains\Places\Http\Requests\AutocompleteRequest;
use App\Domains\Places\Http\Requests\GeocodeRequest;
use App\Domains\Places\Http\Requests\NearbySearchRequest;
use App\Domains\Places\Services\GeocodingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Server-side geocoding proxies (worldwide autocomplete, address geocode,
 * category nearby). The Google key is server-side only; without it each
 * endpoint serves a deterministic curated fallback. Authenticated like the
 * existing Places directory (auth:sanctum). Computed payloads, so plain `data`
 * arrays rather than model Resources.
 */
final class GeocodingController extends Controller
{
    public function __construct(private readonly GeocodingService $geocoding) {}

    public function autocomplete(AutocompleteRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->geocoding->autocomplete((string) $request->input('q'))]);
    }

    public function geocode(GeocodeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->geocoding->geocode((string) $request->input('address'))]);
    }

    public function nearby(NearbySearchRequest $request): JsonResponse
    {
        $lat = $request->filled('lat') ? (float) $request->float('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->float('lng') : null;

        return response()->json([
            'data' => $this->geocoding->nearby((string) $request->input('category'), $lat, $lng),
        ]);
    }
}
