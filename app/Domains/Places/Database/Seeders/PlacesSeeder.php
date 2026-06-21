<?php

declare(strict_types=1);

namespace App\Domains\Places\Database\Seeders;

use App\Domains\Places\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Sample safety POIs (Lagos) for the Emergency Points / Nearby Safe Places
 * screens. Idempotent on name+category.
 */
final class PlacesSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            ['name' => 'Victoria Island Police Station', 'category' => 'police', 'lat' => 6.4281, 'lng' => 3.4219, 'rating' => 4.6, 'reviews_count' => 128, 'is_24_7' => true],
            ['name' => 'Lagos Island General Hospital', 'category' => 'hospital', 'lat' => 6.4550, 'lng' => 3.3940, 'rating' => 4.7, 'reviews_count' => 312, 'is_24_7' => true],
            ['name' => 'Victoria Island Fire Station', 'category' => 'fire_service', 'lat' => 6.4300, 'lng' => 3.4250, 'rating' => 4.4, 'reviews_count' => 64, 'is_24_7' => true],
            ['name' => 'Swift Towing Service', 'category' => 'towing', 'lat' => 6.4400, 'lng' => 3.4300, 'rating' => 4.2, 'reviews_count' => 41, 'is_24_7' => false, 'opens_at' => '06:00:00', 'closes_at' => '22:00:00'],
            ['name' => 'Total Energies Filling Station', 'category' => 'fuel', 'lat' => 6.4350, 'lng' => 3.4180, 'rating' => 4.2, 'reviews_count' => 76, 'is_24_7' => true],
            ['name' => 'Secure Parking – Eko Atlantic', 'category' => 'parking', 'lat' => 6.4200, 'lng' => 3.4100, 'rating' => 4.3, 'reviews_count' => 89, 'is_24_7' => true],
        ];

        foreach ($places as $place) {
            Place::query()->firstOrCreate(
                ['name' => $place['name'], 'category' => $place['category']],
                $place,
            );
        }
    }
}
