<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe Rides — Marketplace & Send. User-scoped (ADR-0001): every offer/delivery
 * belongs to the authenticated rider/sender, no organization.
 *
 * MARKETPLACE (name-your-price): a rider posts a RideOffer with a proposed fare;
 * simulated verified drivers respond with RideBids (accept | counter); the rider
 * accepts one, materialising a real Ride in the Rides domain. The matched driver
 * is a denormalised SNAPSHOT — the canonical record comes with the Driver domain.
 *
 * SENTRIX SEND: a sender books a parcel Delivery (small/medium/large fare
 * multipliers) paid from wallet or Cash-on-Delivery. The courier is the same
 * simulated verified fleet. ALL MONEY IS INTEGER CENTS.
 *
 * Timestamp 2026_06_18_130000 sorts AFTER the Rides (…_100000) and Wallet
 * (…_120000) migrations so the rides/users FKs already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('origin_label')->nullable();
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->string('dest_label')->nullable();
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);

            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('proposed_fare_cents');
            $table->unsignedInteger('fair_estimate_cents');
            $table->string('pricing_flag'); // low | fair | high
            $table->string('status')->default('open'); // open | matched | expired | cancelled

            // Set when a bid is accepted and a Ride is materialised (no FK: the
            // Rides domain owns rides; this is a soft pointer for the rider).
            $table->uuid('matched_ride_id')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('ride_bids', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_offer_id')->constrained('ride_offers')->cascadeOnDelete();

            // Simulated driver SNAPSHOT — nullable id (no FK, the Driver domain
            // owns the canonical record later). String, not uuid: simulated ids
            // like "sd-4f2a9c" aren't UUIDs and this is a loose snapshot reference.
            $table->string('driver_id')->nullable();
            $table->string('driver_name');
            $table->unsignedInteger('amount_cents');
            $table->string('kind'); // accept | counter
            $table->string('status')->default('pending'); // pending | accepted | rejected

            // Append-only-ish: created only (bids never carry an updated_at).
            $table->timestamp('created_at')->nullable();

            $table->index(['ride_offer_id', 'status']);
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('parcel_size'); // small | medium | large
            $table->string('pickup_label');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_label');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);

            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('fare_cents');
            $table->unsignedInteger('cod_amount_cents')->default(0);
            $table->string('payment_method')->default('cod'); // wallet | cod
            $table->string('status')->default('matched'); // requested | matched | in_transit | delivered | cancelled

            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();

            // Simulated courier SNAPSHOT (same vetted fleet; no FK yet).
            $table->string('driver_name')->nullable();
            $table->string('match_code', 4);

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ride_offers ADD CONSTRAINT ride_offers_pricing_flag_check CHECK (pricing_flag IN ('low','fair','high'))");
            DB::statement("ALTER TABLE ride_offers ADD CONSTRAINT ride_offers_status_check CHECK (status IN ('open','matched','expired','cancelled'))");
            DB::statement("ALTER TABLE ride_bids ADD CONSTRAINT ride_bids_kind_check CHECK (kind IN ('accept','counter'))");
            DB::statement("ALTER TABLE ride_bids ADD CONSTRAINT ride_bids_status_check CHECK (status IN ('pending','accepted','rejected'))");
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_parcel_size_check CHECK (parcel_size IN ('small','medium','large'))");
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_payment_method_check CHECK (payment_method IN ('wallet','cod'))");
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_status_check CHECK (status IN ('requested','matched','in_transit','delivered','cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('ride_bids');
        Schema::dropIfExists('ride_offers');
    }
};
