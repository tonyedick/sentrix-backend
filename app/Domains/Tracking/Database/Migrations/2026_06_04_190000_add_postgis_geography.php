<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables PostGIS and adds generated `geography(Point, 4326)` columns for spatial
 * queries (proximity now, geofence later):
 *
 *  - trips.last_location      — the trip's current position (proximity: "active
 *    trips near point X"); null-safe (only set once a fix has been recorded).
 *  - trip_locations.location  — every fix on the (partitioned) track.
 *
 * Columns are GENERATED ... STORED from the existing lat/lng so they can never
 * drift, and each gets a GiST index. PostgreSQL-only; requires a PostGIS image.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD COLUMN last_location geography(Point, 4326)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN last_lat IS NOT NULL AND last_lng IS NOT NULL
                        THEN ST_SetSRID(ST_MakePoint(last_lng, last_lat), 4326)::geography
                        ELSE NULL
                    END
                ) STORED
        SQL);
        DB::statement('CREATE INDEX trips_last_location_gist ON trips USING gist (last_location)');

        DB::statement(<<<'SQL'
            ALTER TABLE trip_locations ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography) STORED
        SQL);
        DB::statement('CREATE INDEX trip_locations_location_gist ON trip_locations USING gist (location)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS trip_locations_location_gist');
        DB::statement('ALTER TABLE trip_locations DROP COLUMN IF EXISTS location');
        DB::statement('DROP INDEX IF EXISTS trips_last_location_gist');
        DB::statement('ALTER TABLE trips DROP COLUMN IF EXISTS last_location');

        // The extension is intentionally left installed — other features may rely
        // on it, and dropping it would cascade to any spatial objects.
    }
};
