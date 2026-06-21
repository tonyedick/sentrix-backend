<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Places / POI directory (Emergency Points + Nearby Safe Places). Reference data,
 * region/geo-scoped — not per-user (ADR-0001). PostGIS geography + GiST for
 * nearest-first queries in metres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->boolean('is_24_7')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('category');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement(<<<'SQL'
            ALTER TABLE places ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography) STORED
        SQL);
        DB::statement('CREATE INDEX places_location_gist ON places USING gist (location)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS places_location_gist');
            DB::statement('ALTER TABLE places DROP COLUMN IF EXISTS location');
        }

        Schema::dropIfExists('places');
    }
};
