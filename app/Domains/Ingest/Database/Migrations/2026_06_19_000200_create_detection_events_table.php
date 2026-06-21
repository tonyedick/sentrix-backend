<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * detection_events — the raw, append-leaning record of every signal the Ingest
 * pipeline assesses: camera/vision detections, native detection events, and
 * SafeSignal cross-product reports. Each row captures WHAT was seen and the
 * decision the engine made (severity / risk_score / triggered), and links to
 * the Incident it opened (if any).
 *
 *  - organization_id : tenant scoping (cascade with the org).
 *  - source          : detection | vision | signal (CHECK-pinned on pgsql).
 *  - product         : originating product, e.g. omni | go | fleet (free-form).
 *  - camera_source_id: the originating camera (real uuid; NO FK — cameras live
 *                      outside this domain). Guard uuid comparisons with
 *                      Str::isUuid before querying on Postgres.
 *  - type            : normalized detection label, e.g. weapon_detected.
 *  - severity        : decision severity (critical|high|medium|low|info).
 *  - risk_score      : 0-100 integer risk.
 *  - triggered       : whether the decision opened an incident.
 *  - incident_id     : the opened incident (plain uuid pointer; no cross-domain FK).
 *  - site / zone     : coarse location labels.
 *  - lat / lng       : precise coordinates (decimal); public feed coarsens these.
 *  - payload         : the raw provider/detection envelope.
 *  - received_at     : when the signal was ingested (ordering).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('source'); // detection | vision | signal
            $table->string('product')->nullable(); // omni | go | fleet | ...

            // Real camera id, but cameras are not owned by this domain — no FK.
            $table->uuid('camera_source_id')->nullable();

            $table->string('type')->nullable();
            $table->string('severity'); // critical | high | medium | low | info
            $table->unsignedTinyInteger('risk_score')->default(0); // 0-100
            $table->boolean('triggered')->default(false);

            // The incident this event opened, if any (decoupled uuid pointer).
            $table->uuid('incident_id')->nullable();

            $table->string('site')->nullable();
            $table->string('zone')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->text('summary')->nullable();
            $table->jsonb('payload')->nullable();

            $table->timestamp('received_at');
            $table->timestamps();

            // Recent events for an org (feeds + ordering).
            $table->index(['organization_id', 'received_at']);
            // Triggered events for an org (the ones that became incidents).
            $table->index(['organization_id', 'triggered']);
            // Camera-scoped lookups (decoupled pointer, still queried).
            $table->index('camera_source_id');
            $table->index('incident_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE detection_events ADD CONSTRAINT detection_events_source_check CHECK (source IN ('detection','vision','signal'))");
        DB::statement("ALTER TABLE detection_events ADD CONSTRAINT detection_events_severity_check CHECK (severity IN ('critical','high','medium','low','info'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE detection_events DROP CONSTRAINT IF EXISTS detection_events_source_check');
            DB::statement('ALTER TABLE detection_events DROP CONSTRAINT IF EXISTS detection_events_severity_check');
        }

        Schema::dropIfExists('detection_events');
    }
};
