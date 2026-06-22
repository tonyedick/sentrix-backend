<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Requests;

use App\Domains\Places\Services\GeocodingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Category nearby search for the geocoding proxy (distinct from the PostGIS
 * directory NearbyPlacesRequest). The category set is the Google/curated POI
 * taxonomy (cafe|restaurant|atm|pharmacy|gas_station|lodging|supermarket).
 */
final class NearbySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(GeocodingService::categories())],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }
}
