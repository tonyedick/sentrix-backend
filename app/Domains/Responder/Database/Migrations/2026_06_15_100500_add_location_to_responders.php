<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the denormalised current position to responders and the PostGIS
 * geography columns used for proximity dispatch — reusing the Tracking pattern:
 * GENERATED ... STORED from lat/lng (so they cannot drift), each with a GiST
 * index. PostgreSQL-only; the plain columns are portable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table): void {
            $table->decimal('last_lat', 10, 7)->nullable()->after('on_duty');
            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $table->timestamp('last_seen_at')->nullable()->after('last_lng');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        DB::statement(<<<'SQL'
            ALTER TABLE responders ADD COLUMN last_location geography(Point, 4326)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN last_lat IS NOT NULL AND last_lng IS NOT NULL
                        THEN ST_SetSRID(ST_MakePoint(last_lng, last_lat), 4326)::geography
                        ELSE NULL
                    END
                ) STORED
        SQL);
        DB::statement('CREATE INDEX responders_last_location_gist ON responders USING gist (last_location)');

        DB::statement(<<<'SQL'
            ALTER TABLE responder_locations ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography) STORED
        SQL);
        DB::statement('CREATE INDEX responder_locations_location_gist ON responder_locations USING gist (location)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS responder_locations_location_gist');
            DB::statement('ALTER TABLE responder_locations DROP COLUMN IF EXISTS location');
            DB::statement('DROP INDEX IF EXISTS responders_last_location_gist');
            DB::statement('ALTER TABLE responders DROP COLUMN IF EXISTS last_location');
        }

        Schema::table('responders', function (Blueprint $table): void {
            $table->dropColumn(['last_lat', 'last_lng', 'last_seen_at']);
        });
    }
};
