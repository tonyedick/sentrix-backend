<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, incident-scoped operational timeline. A durable projection of
 * everything that happens to an incident (status changes, dispatch events,
 * notifications, AI annotations) so the incident console reads one paginatable
 * stream rather than merging sources at request time.
 *
 *  - organization_id : tenant scoping + the org-wide activity feed.
 *  - incident_id      : the timeline's owner (cascade).
 *  - type             : dotted event key (free-form, e.g. incident.opened,
 *                       assignment.responder_accepted, ai.risk_assessed).
 *  - source           : producing domain — filtering + future realtime routing
 *                       (CHECK-constrained to the known set).
 *  - actor_id         : who performed it (auditability); null = system.
 *  - subject_type/id  : the related record (assignment, responder line, …) as a
 *                       decoupled polymorphic-lite pointer — no cross-domain FK.
 *  - payload          : entry detail; the future-AI context seam + audit context.
 *  - occurred_at      : business time, used for ordering (append-only; no updated_at).
 *
 * High-volume reads are covered by (incident_id, occurred_at) and
 * (organization_id, occurred_at); index count is kept lean because the table is
 * append-heavy. RANGE-partition-ready by occurred_at (monthly) if volume warrants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_timeline_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('incident_id')->constrained('incidents')->cascadeOnDelete();

            $table->string('type');
            $table->string('source');

            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // Decoupled reference to the related record in any domain (no FK).
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();

            $table->jsonb('payload')->nullable();

            $table->timestamp('occurred_at');
            // Append-only: insert time only, no updated_at.
            $table->timestamp('created_at')->nullable();

            // Hot path: one incident's timeline in chronological order (paginated).
            $table->index(['incident_id', 'occurred_at']);
            // Org-wide activity feed / realtime fan-out ordering.
            $table->index(['organization_id', 'occurred_at']);
            // "What happened to this assignment / responder line".
            $table->index(['subject_type', 'subject_id']);
            // "What did this user do" (audit).
            $table->index('actor_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Source is a small, known set (extend here when a new producer is added).
        DB::statement("ALTER TABLE incident_timeline_entries ADD CONSTRAINT incident_timeline_entries_source_check CHECK (source IN ('incident', 'assignment', 'notification', 'ai', 'system'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE incident_timeline_entries DROP CONSTRAINT IF EXISTS incident_timeline_entries_source_check');
        }

        Schema::dropIfExists('incident_timeline_entries');
    }
};
