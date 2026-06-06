<?php

declare(strict_types=1);

use App\Domains\Tracking\Support\TripLocationPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * High-write location track. Append-only time-series.
 *
 * On PostgreSQL it is RANGE-partitioned by month (with a DEFAULT catch-all so an
 * insert can never fail on a missing partition). Dedupe is by the device-assigned
 * `client_fix_id` together with `recorded_at` (the partition key) — a re-sent fix
 * carries the same pair, so `insertOrIgnore` makes ingestion idempotent under the
 * retries that flaky networks guarantee. No FK on this hot path; trip validity is
 * enforced at ingest time. `organization_id`/`user_id` are denormalised for
 * tenant-scoped reads without joining `trips`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE trip_locations (
                    id uuid NOT NULL,
                    trip_id uuid NOT NULL,
                    organization_id uuid NOT NULL,
                    user_id uuid NOT NULL,
                    client_fix_id uuid NOT NULL,
                    lat double precision NOT NULL,
                    lng double precision NOT NULL,
                    accuracy double precision NULL,
                    speed double precision NULL,
                    heading double precision NULL,
                    recorded_at timestamp(0) without time zone NOT NULL,
                    received_at timestamp(0) without time zone NOT NULL,
                    created_at timestamp(0) without time zone NULL,
                    PRIMARY KEY (id, recorded_at),
                    UNIQUE (client_fix_id, recorded_at)
                ) PARTITION BY RANGE (recorded_at)
            SQL);

            DB::statement('CREATE TABLE trip_locations_default PARTITION OF trip_locations DEFAULT');

            TripLocationPartitions::ensureUpcoming(1);

            DB::statement('CREATE INDEX trip_locations_trip_recorded_index ON trip_locations (trip_id, recorded_at DESC)');
            DB::statement('CREATE INDEX trip_locations_org_recorded_index ON trip_locations (organization_id, recorded_at DESC)');

            return;
        }

        // Portable fallback (non-PostgreSQL): a plain table with the same shape.
        Schema::create('trip_locations', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('trip_id');
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->uuid('client_fix_id');
            $table->double('lat');
            $table->double('lng');
            $table->double('accuracy')->nullable();
            $table->double('speed')->nullable();
            $table->double('heading')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->timestamp('created_at')->nullable();

            $table->primary(['id', 'recorded_at']);
            $table->unique(['client_fix_id', 'recorded_at']);
            $table->index(['trip_id', 'recorded_at']);
            $table->index(['organization_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
    }
};
