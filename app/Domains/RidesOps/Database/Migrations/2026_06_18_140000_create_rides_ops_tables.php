<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rides Ops — the platform/staff-scoped operations dashboard for Safe Rides.
 *
 * This domain mostly READS the Rides, DriverOnboarding and RidesMarket domains;
 * it owns exactly ONE table of its own: manual surge overrides. An operator can
 * PIN a demand multiplier (optionally per zone); the "current manual surge" is
 * the latest active row, and releasing simply sets active=false. There is no
 * enum-like column here, so no driver-guarded CHECK is needed.
 *
 * Timestamp 2026_06_18_140000 sorts AFTER the RidesMarket migration
 * (…_130000) so any users/rides FKs already exist.
 *
 * ALL MONEY IS INTEGER CENTS (no money columns live here, but the dashboards
 * this domain serves read final_fare_cents etc. as integers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surge_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Optional operating zone the surge applies to (null = network-wide).
            $table->string('zone')->nullable();
            $table->decimal('multiplier', 3, 2);
            $table->boolean('active')->default(true);
            // The platform-staff user who pinned the surge (null when system).
            $table->foreignUuid('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            // Append-only history: created_at only (a release flips `active`, it
            // does not update the row's content), so no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['active', 'created_at']);
            $table->index('zone');
            $table->index('set_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_overrides');
    }
};
