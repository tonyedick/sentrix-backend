<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Safe Rides — Driver onboarding (registration -> documents -> inspection ->
 * hardware install -> activation) plus the staff review surface. User-scoped:
 * a `drivers` row belongs 1:1 to the authenticated user (no organization). The
 * staff side is platform/SuperAdmin-gated, not org-scoped.
 *
 * Ordered after the Rides migration (2026_06_18_100000) so the simulated-match
 * Rides core lands first; this is 2026_06_18_110000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // One driver profile per user.
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('stage')->default('documents_review'); // documents_review | documents_approved | inspection_booked | vetting | active | rejected | suspended
            $table->string('availability')->default('offline');   // offline | online | on_trip

            // Staff review.
            $table->uuid('reviewer_id')->nullable(); // user uuid of the staff reviewer (no FK; staff identity is platform-side)
            $table->text('review_note')->nullable();

            // Ecosystem signal: telematics-backed safety score from Sentrix Fleet.
            // Env-gated + stubbed to null for now (Fleet not wired yet).
            $table->unsignedInteger('fleet_safety_score')->nullable();

            // Live-driver stats (denormalised; populated once dispatch lands).
            $table->unsignedInteger('trips_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();

            // Vehicle.
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_color')->nullable();

            // Sentrix devices installed at the vetting center on inspection pass.
            $table->jsonb('installed_hardware')->nullable();

            $table->timestamps();

            $table->index('stage');
            $table->index('availability');
        });

        Schema::create('driver_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('type'); // license | insurance | vehicle_registration | roadworthiness | other
            $table->string('url');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('note')->nullable();
            $table->uuid('reviewed_by')->nullable(); // staff user uuid (no FK; platform-side)
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });

        Schema::create('vetting_centers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->jsonb('slots'); // list<string> of available slot strings
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignUuid('vetting_center_id')->nullable()->constrained('vetting_centers')->nullOnDelete();
            $table->string('booked_slot');
            $table->string('status')->default('booked'); // booked | passed | failed
            $table->jsonb('checklist')->nullable();
            $table->uuid('decided_by')->nullable(); // staff user uuid (no FK; platform-side)
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_stage_check CHECK (stage IN ('documents_review','documents_approved','inspection_booked','vetting','active','rejected','suspended'))");
            DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_availability_check CHECK (availability IN ('offline','online','on_trip'))");
            DB::statement("ALTER TABLE driver_documents ADD CONSTRAINT driver_documents_type_check CHECK (type IN ('license','insurance','vehicle_registration','roadworthiness','other'))");
            DB::statement("ALTER TABLE driver_documents ADD CONSTRAINT driver_documents_status_check CHECK (status IN ('pending','approved','rejected'))");
            DB::statement("ALTER TABLE inspections ADD CONSTRAINT inspections_status_check CHECK (status IN ('booked','passed','failed'))");
        }

        // Seed the physical Sentrix Vetting Centers (demo roster; production loads
        // per city). Done here in the migration so a fresh DB is immediately usable
        // by GET /driver/vetting-centers without a separate seeder run.
        $now = now();
        DB::table('vetting_centers')->insert([
            [
                'id' => (string) Str::orderedUuid(),
                'name' => 'Sentrix Vetting Center — Ikeja',
                'address' => '14 Allen Avenue, Ikeja, Lagos',
                'lat' => 6.6018,
                'lng' => 3.3515,
                'slots' => json_encode(['Mon 09:00', 'Mon 11:00', 'Tue 14:00', 'Wed 10:00']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'name' => 'Sentrix Vetting Center — Lekki',
                'address' => '2 Admiralty Way, Lekki Phase 1, Lagos',
                'lat' => 6.4452,
                'lng' => 3.4736,
                'slots' => json_encode(['Mon 13:00', 'Tue 09:00', 'Thu 15:00', 'Fri 10:00']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'name' => 'Sentrix Vetting Center — Abuja Central',
                'address' => '7 Ahmadu Bello Way, Garki, Abuja',
                'lat' => 9.0336,
                'lng' => 7.4892,
                'slots' => json_encode(['Tue 10:00', 'Wed 12:00', 'Thu 09:00']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('vetting_centers');
        Schema::dropIfExists('driver_documents');
        Schema::dropIfExists('drivers');
    }
};
