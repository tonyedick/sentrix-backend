<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe Rides — ride booking/lifecycle + in-ride safety. User-scoped (ADR-0001):
 * every ride belongs to the authenticated rider, no organization. The matched
 * driver is stored as a denormalised SNAPSHOT (driver_*); the real Driver domain
 * (onboarding, pool, dispatch) integrates later and will own the canonical record
 * — hence no driver FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('ride_class'); // go_safe | comfort | go_xl
            $table->string('status')->default('requested'); // requested | matched | arriving | in_progress | completed | cancelled

            $table->string('origin_label')->nullable();
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->string('dest_label')->nullable();
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);

            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('fare_estimate_cents')->default(0);
            $table->unsignedInteger('final_fare_cents')->nullable();
            $table->unsignedInteger('tip_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('surge_multiplier', 3, 2)->default(1.00);
            $table->string('payment_method')->default('cash'); // cash | card | wallet
            $table->string('match_code', 4);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('cancel_reason')->nullable();

            // Matched-driver SNAPSHOT (populated on request by a simulated match;
            // no FK — the Driver domain comes later and will own the real record).
            // String (not uuid): simulated driver ids like "sd-4f2a9c" aren't UUIDs,
            // and this is a loose snapshot reference, never a real foreign key.
            $table->string('driver_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_plate')->nullable();
            $table->decimal('driver_lat', 10, 7)->nullable();
            $table->decimal('driver_lng', 10, 7)->nullable();
            $table->unsignedInteger('driver_eta_minutes')->nullable();
            $table->unsignedInteger('driver_speed_kph')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'requested_at']);
        });

        Schema::create('ride_safeties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // 1:1 with a ride.
            $table->foreignUuid('ride_id')->unique()->constrained('rides')->cascadeOnDelete();
            $table->boolean('armed')->default(false);
            $table->boolean('recording')->default(false);
            $table->boolean('guardians_notified')->default(false);
            $table->boolean('off_route')->default(false);
            $table->boolean('overdue')->default(false);
            $table->boolean('check_in_due')->default(false);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->timestamps();
        });

        Schema::create('ride_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->string('kind'); // video | audio | photo
            $table->string('url');
            $table->timestamp('recorded_at');
            // Append-only log: created_at only, no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index('ride_id');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_ride_class_check CHECK (ride_class IN ('go_safe','comfort','go_xl'))");
            DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('requested','matched','arriving','in_progress','completed','cancelled'))");
            DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_payment_method_check CHECK (payment_method IN ('cash','card','wallet'))");
            DB::statement("ALTER TABLE ride_evidence ADD CONSTRAINT ride_evidence_kind_check CHECK (kind IN ('video','audio','photo'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_evidence');
        Schema::dropIfExists('ride_safeties');
        Schema::dropIfExists('rides');
    }
};
