<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Durable last-known position on the trip itself — the authoritative snapshot for
 * dashboards and the source the staleness sweep queries. (Redis is used as a hot
 * read-cache + broadcast throttle, not as the source of truth for a safety-critical
 * position.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->timestamp('last_location_at')->nullable()->after('expected_arrival_at');
            $table->double('last_lat')->nullable()->after('last_location_at');
            $table->double('last_lng')->nullable()->after('last_lat');
        });

        // Drives the staleness sweep: active trips whose last fix is too old.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX trips_stale_sweep_index ON trips (last_location_at) WHERE status IN ('active', 'overdue')");
        } else {
            Schema::table('trips', fn (Blueprint $table) => $table->index('last_location_at', 'trips_last_location_at_index'));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS trips_stale_sweep_index');
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn(['last_location_at', 'last_lat', 'last_lng']);
        });
    }
};
