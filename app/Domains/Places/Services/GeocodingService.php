<?php

declare(strict_types=1);

namespace App\Domains\Places\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server-side geocoding proxies for the consumer trip-planning surface:
 * worldwide autocomplete, address geocode, and category nearby search.
 *
 * The Google Maps Platform key lives ONLY in config('sentrix.places.google_api_key')
 * (env SENTRIX_PLACES_GOOGLE_API_KEY) and is never returned to the client. The
 * service is env-gated and fail-safe:
 *
 *   - key UNSET (tests, local) -> a deterministic CURATED fallback answers, so
 *     the endpoints always work offline;
 *   - key SET -> the matching Google endpoint is proxied via Laravel Http;
 *   - any Google/network error or non-OK status -> we fall back to curated.
 *     We never 500 on a downstream failure.
 *
 * Mirrors SentrixGoBackend/app/routers/places.py (autocomplete/geocode/nearby).
 */
final readonly class GeocodingService
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Worldwide curated destinations (the no-key autocomplete/geocode corpus).
     *
     * @var list<array{name:string,address:string,lat:float,lng:float}>
     */
    private const DESTINATIONS = [
        ['name' => 'Ikeja City Mall', 'address' => 'Obafemi Awolowo Way, Alausa', 'lat' => 6.6018, 'lng' => 3.3515],
        ['name' => 'Murtala Muhammed Airport', 'address' => 'Ikeja, Lagos', 'lat' => 6.5774, 'lng' => 3.3212],
        ['name' => 'Lekki Phase 1', 'address' => 'Admiralty Way, Lekki', 'lat' => 6.4474, 'lng' => 3.4709],
        ['name' => 'Victoria Island', 'address' => 'Adeola Odeku, VI', 'lat' => 6.4281, 'lng' => 3.4219],
    ];

    /**
     * Category -> curated POIs. Keys match the validated `category` values.
     *
     * @var array<string, list<array{name:string,address:string,rating:?float,open_now:?bool,lat:float,lng:float}>>
     */
    private const CATEGORIES = [
        'cafe' => [
            ['name' => 'Cafe Neo', 'address' => 'Adeola Odeku St', 'rating' => 4.5, 'open_now' => true, 'lat' => 6.4290, 'lng' => 3.4220],
            ['name' => 'Bottega Coffee', 'address' => 'Idejo Street', 'rating' => 4.3, 'open_now' => true, 'lat' => 6.4302, 'lng' => 3.4231],
            ['name' => 'Cafe One', 'address' => 'Ozumba Mbadiwe Ave', 'rating' => 4.2, 'open_now' => null, 'lat' => 6.4255, 'lng' => 3.4280],
        ],
        'restaurant' => [
            ['name' => 'The Yellow Chilli', 'address' => 'Oju Olobun Close', 'rating' => 4.4, 'open_now' => true, 'lat' => 6.4298, 'lng' => 3.4215],
            ['name' => 'Mama Put Kitchen', 'address' => 'Awolowo Road', 'rating' => 4.6, 'open_now' => true, 'lat' => 6.4360, 'lng' => 3.4180],
            ['name' => 'Ocean Basket', 'address' => 'Victoria Island', 'rating' => 4.1, 'open_now' => null, 'lat' => 6.4281, 'lng' => 3.4219],
        ],
        'atm' => [
            ['name' => 'GTBank ATM', 'address' => 'Adeola Hopewell St', 'rating' => 4.0, 'open_now' => true, 'lat' => 6.4288, 'lng' => 3.4225],
            ['name' => 'Access Bank ATM', 'address' => 'Saka Tinubu St', 'rating' => 3.9, 'open_now' => true, 'lat' => 6.4301, 'lng' => 3.4240],
            ['name' => 'Zenith Bank ATM', 'address' => 'Ahmadu Bello Way', 'rating' => 4.1, 'open_now' => true, 'lat' => 6.4270, 'lng' => 3.4260],
        ],
        'pharmacy' => [
            ['name' => 'HealthPlus Pharmacy', 'address' => 'Adeola Odeku St', 'rating' => 4.5, 'open_now' => true, 'lat' => 6.4291, 'lng' => 3.4221],
            ['name' => 'MedPlus', 'address' => 'Bishop Aboyade Cole', 'rating' => 4.3, 'open_now' => true, 'lat' => 6.4310, 'lng' => 3.4250],
            ['name' => 'Alpha Pharmacy', 'address' => 'Karimu Kotun St', 'rating' => 4.0, 'open_now' => null, 'lat' => 6.4283, 'lng' => 3.4238],
        ],
        'gas_station' => [
            ['name' => 'Total Energies', 'address' => 'Ahmadu Bello Way', 'rating' => 4.2, 'open_now' => true, 'lat' => 6.4272, 'lng' => 3.4262],
            ['name' => 'Mobil Filling Station', 'address' => 'Ozumba Mbadiwe', 'rating' => 4.0, 'open_now' => true, 'lat' => 6.4258, 'lng' => 3.4278],
            ['name' => 'NNPC Mega Station', 'address' => 'Ozumba Mbadiwe Ave', 'rating' => 4.1, 'open_now' => true, 'lat' => 6.4256, 'lng' => 3.4281],
        ],
        'lodging' => [
            ['name' => 'Eko Hotels & Suites', 'address' => 'Adetokunbo Ademola St', 'rating' => 4.5, 'open_now' => true, 'lat' => 6.4225, 'lng' => 3.4290],
            ['name' => 'The George', 'address' => 'Kofo Abayomi St', 'rating' => 4.6, 'open_now' => true, 'lat' => 6.4267, 'lng' => 3.4233],
            ['name' => 'Lagos Continental', 'address' => 'Kofo Abayomi St', 'rating' => 4.4, 'open_now' => true, 'lat' => 6.4265, 'lng' => 3.4235],
        ],
        'supermarket' => [
            ['name' => 'Shoprite', 'address' => 'Bishop Aboyade Cole', 'rating' => 4.4, 'open_now' => true, 'lat' => 6.4312, 'lng' => 3.4252],
            ['name' => 'SPAR', 'address' => 'Ligali Ayorinde St', 'rating' => 4.3, 'open_now' => true, 'lat' => 6.4330, 'lng' => 3.4270],
            ['name' => 'Ebeano Supermarket', 'address' => 'Adeola Odeku St', 'rating' => 4.2, 'open_now' => true, 'lat' => 6.4292, 'lng' => 3.4223],
        ],
    ];

    /**
     * Worldwide destination autocomplete.
     *
     * @return list<array{description:string,place_id:?string,lat:?float,lng:?float}>
     */
    public function autocomplete(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $key = $this->apiKey();

        if ($key === null) {
            return $this->curatedAutocomplete($query);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(
                'https://maps.googleapis.com/maps/api/place/autocomplete/json',
                ['input' => $query, 'key' => $key],
            );

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            if (($data['status'] ?? null) !== 'OK' || ! is_array($data['predictions'] ?? null)) {
                return $this->curatedAutocomplete($query);
            }

            $out = [];

            foreach (array_slice($data['predictions'], 0, 6) as $prediction) {
                if (! is_array($prediction)) {
                    continue;
                }

                $out[] = [
                    'description' => (string) ($prediction['description'] ?? ''),
                    'place_id' => isset($prediction['place_id']) ? (string) $prediction['place_id'] : null,
                    'lat' => null,
                    'lng' => null,
                ];
            }

            return $out === [] ? $this->curatedAutocomplete($query) : $out;
        } catch (Throwable) {
            return $this->curatedAutocomplete($query);
        }
    }

    /**
     * Geocode an address to coordinates.
     *
     * @return array{lat:float,lng:float,formatted_address:string}|array{}
     */
    public function geocode(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [];
        }

        $key = $this->apiKey();

        if ($key === null) {
            return $this->curatedGeocode($address);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                ['address' => $address, 'key' => $key],
            );

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];
            $results = is_array($data['results'] ?? null) ? $data['results'] : [];
            $first = $results[0] ?? null;

            if (! is_array($first)) {
                return $this->curatedGeocode($address);
            }

            $location = (is_array($first['geometry'] ?? null) ? ($first['geometry']['location'] ?? null) : null);

            if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
                return $this->curatedGeocode($address);
            }

            return [
                'lat' => (float) $location['lat'],
                'lng' => (float) $location['lng'],
                'formatted_address' => (string) ($first['formatted_address'] ?? $address),
            ];
        } catch (Throwable) {
            return $this->curatedGeocode($address);
        }
    }

    /**
     * Nearby POIs of a category, ranked nearest-first when an origin is given.
     *
     * @return list<array{name:string,address:string,category:string,rating:?float,open_now:?bool,lat:float,lng:float,distance_m:?int}>
     */
    public function nearby(string $category, ?float $lat = null, ?float $lng = null): array
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            return [];
        }

        $key = $this->apiKey();

        if ($key !== null) {
            $proxied = $this->googleNearby($category, $key, $lat, $lng);

            if ($proxied !== null) {
                return $proxied;
            }
        }

        return $this->curatedNearby($category, $lat, $lng);
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_keys(self::CATEGORIES);
    }

    private function apiKey(): ?string
    {
        $key = config('sentrix.places.google_api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @return list<array{description:string,place_id:?string,lat:?float,lng:?float}>
     */
    private function curatedAutocomplete(string $query): array
    {
        $needle = Str::lower($query);

        $matches = array_values(array_filter(
            self::DESTINATIONS,
            static fn (array $place): bool => str_contains(Str::lower($place['name']), $needle)
                || str_contains(Str::lower($place['address']), $needle),
        ));

        return array_map(
            static fn (array $place): array => [
                'description' => $place['name'].', '.$place['address'],
                'place_id' => null,
                'lat' => $place['lat'],
                'lng' => $place['lng'],
            ],
            array_slice($matches, 0, 6),
        );
    }

    /**
     * @return array{lat:float,lng:float,formatted_address:string}|array{}
     */
    private function curatedGeocode(string $address): array
    {
        $needle = Str::lower($address);

        foreach (self::DESTINATIONS as $place) {
            if (str_contains(Str::lower($place['name']), $needle)
                || str_contains(Str::lower($place['address']), $needle)
                || str_contains($needle, Str::lower($place['name']))) {
                return [
                    'lat' => $place['lat'],
                    'lng' => $place['lng'],
                    'formatted_address' => $place['name'].', '.$place['address'],
                ];
            }
        }

        return [];
    }

    /**
     * @return list<array{name:string,address:string,category:string,rating:?float,open_now:?bool,lat:float,lng:float,distance_m:?int}>
     */
    private function curatedNearby(string $category, ?float $lat, ?float $lng): array
    {
        $rows = array_map(
            function (array $poi) use ($category, $lat, $lng): array {
                $distance = ($lat !== null && $lng !== null)
                    ? (int) round($this->haversineMeters($lat, $lng, $poi['lat'], $poi['lng']))
                    : null;

                return [
                    'name' => $poi['name'],
                    'address' => $poi['address'],
                    'category' => $category,
                    'rating' => $poi['rating'],
                    'open_now' => $poi['open_now'],
                    'lat' => $poi['lat'],
                    'lng' => $poi['lng'],
                    'distance_m' => $distance,
                ];
            },
            self::CATEGORIES[$category],
        );

        if ($lat !== null && $lng !== null) {
            usort($rows, static fn (array $a, array $b): int => ($a['distance_m'] ?? PHP_INT_MAX) <=> ($b['distance_m'] ?? PHP_INT_MAX));
        }

        return array_values($rows);
    }

    /**
     * @return list<array{name:string,address:string,category:string,rating:?float,open_now:?bool,lat:float,lng:float,distance_m:?int}>|null
     */
    private function googleNearby(string $category, string $key, ?float $lat, ?float $lng): ?array
    {
        try {
            $params = ['query' => $category.' near me', 'type' => $category, 'key' => $key];

            if ($lat !== null && $lng !== null) {
                $params['location'] = $lat.','.$lng;
                $params['radius'] = '3000';
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(
                'https://maps.googleapis.com/maps/api/place/textsearch/json',
                $params,
            );

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            if (($data['status'] ?? null) !== 'OK' || ! is_array($data['results'] ?? null)) {
                return null;
            }

            $out = [];

            foreach (array_slice($data['results'], 0, 4) as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $location = is_array($result['geometry'] ?? null) ? ($result['geometry']['location'] ?? null) : null;
                $openNow = is_array($result['opening_hours'] ?? null) ? ($result['opening_hours']['open_now'] ?? null) : null;

                $out[] = [
                    'name' => (string) ($result['name'] ?? ''),
                    'address' => (string) ($result['formatted_address'] ?? $result['vicinity'] ?? ''),
                    'category' => $category,
                    'rating' => isset($result['rating']) ? (float) $result['rating'] : null,
                    'open_now' => is_bool($openNow) ? $openNow : null,
                    'lat' => is_array($location) && isset($location['lat']) ? (float) $location['lat'] : 0.0,
                    'lng' => is_array($location) && isset($location['lng']) ? (float) $location['lng'] : 0.0,
                    'distance_m' => null,
                ];
            }

            return $out === [] ? null : $out;
        } catch (Throwable) {
            return null;
        }
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
