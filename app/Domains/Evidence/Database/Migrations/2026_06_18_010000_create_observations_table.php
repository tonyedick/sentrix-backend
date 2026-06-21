<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence: the forensic vault's metadata index.
 *
 * Each row is one OBSERVATION — a continuously-captured, searchable trace of
 * something an AI surveillance estate detected (a face, a vehicle, a plate, an
 * object, a scene frame, an audio/thermal/behaviour/sensor event). Organization-
 * scoped (tenant-isolated). Media bytes live elsewhere; these rows hold the
 * forensic descriptor bag (`attributes`) plus the refs and flags investigators
 * search and act on (legal hold, bookmark, seal, retention tier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Optional originating camera (VisionGuard's camera_sources). Nullable
            // because uploads / sensor / pipeline captures may have no registered
            // source; the source itself is user-scoped, this is just a reference.
            $table->foreignUuid('camera_source_id')->nullable()->constrained('camera_sources')->nullOnDelete();

            $table->string('kind'); // face|vehicle|plate|object|scene|audio|behavior|thermal|sensor
            $table->string('label')->nullable();

            // Free-form forensic descriptor bag: colour/clothing/age/make/model/
            // plate/items/etc. Faceted search matches inside this with `->>`.
            $table->jsonb('attributes')->nullable();

            // Denormalized from attributes for fast vehicle/journey lookup.
            $table->string('plate')->nullable();

            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('severity')->nullable(); // critical|high|medium|low|info

            $table->string('snapshot_url')->nullable();
            $table->string('clip_url')->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamp('observed_at');

            $table->boolean('hold')->default(false);       // legal hold
            $table->boolean('bookmarked')->default(false);
            $table->boolean('sealed')->default(false);
            $table->string('retention_tier')->default('hot'); // hot|warm|cold|archived

            $table->timestamps();

            // Hot read paths: the vault is browsed/filtered by org + time, kind,
            // plate (vehicle journey), and legal-hold status.
            $table->index(['organization_id', 'observed_at']);
            $table->index(['organization_id', 'kind']);
            $table->index(['organization_id', 'plate']);
            $table->index(['organization_id', 'hold']);
            $table->index('camera_source_id');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE observations ADD CONSTRAINT observations_kind_check CHECK (kind IN ('face','vehicle','plate','object','scene','audio','behavior','thermal','sensor'))");
            DB::statement("ALTER TABLE observations ADD CONSTRAINT observations_severity_check CHECK (severity IS NULL OR severity IN ('critical','high','medium','low','info'))");
            DB::statement("ALTER TABLE observations ADD CONSTRAINT observations_retention_tier_check CHECK (retention_tier IN ('hot','warm','cold','archived'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
