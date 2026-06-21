<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware device registry — physical security hardware (gate scanners, panic
 * buttons, sensors, controllers, beacons) deployed across an organization's
 * sites and zones. Distinct from the push-notification "devices" used by the
 * Identity domain; hence the hardware_devices table name.
 *
 * Organization-scoped. A device's serial is unique within its organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // The member who registered the device (nullable for imported/automated registration).
            $table->foreignUuid('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('other');       // gate_scanner | panic_button | sensor | controller | beacon | other
            $table->string('serial');
            $table->string('name');
            $table->string('site')->nullable();
            $table->string('zone')->nullable();
            $table->string('status')->default('active');     // active | offline | maintenance | retired
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // A serial is unique within an organization (the registry lookup key).
            $table->unique(['organization_id', 'serial']);
            // Hot reads: an org's devices by status, by site, and FK index.
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'site']);
            $table->index('registered_by');
        });

        // PostgreSQL: pin enum-like columns to their allowed values at the DB
        // layer so an invalid state can't exist even via raw SQL. Mirrors the
        // project's schema-integrity convention; driver-guarded for portability.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE hardware_devices ADD CONSTRAINT hardware_devices_kind_check CHECK (kind IN ('gate_scanner','panic_button','sensor','controller','beacon','other'))");
            DB::statement("ALTER TABLE hardware_devices ADD CONSTRAINT hardware_devices_status_check CHECK (status IN ('active','offline','maintenance','retired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_devices');
    }
};
