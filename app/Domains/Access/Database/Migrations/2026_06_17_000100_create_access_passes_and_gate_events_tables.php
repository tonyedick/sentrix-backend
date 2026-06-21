<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Access management — visitor passes + the gate event log.
 *
 * A PASS is a time-bound access credential a host (resident/member/staff) or a
 * manager mints for a VISITOR. A gate officer verifies it with a scan, which
 * appends an immutable gate event. Single-use passes are consumed on first
 * granted entry. Both tables are organization-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_passes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // The host who issued the pass (the resident/member vouching for the guest).
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 12);
            $table->string('visitor_name');
            $table->string('type')->default('single');     // single | recurring | domestic
            $table->string('status')->default('active');    // active | consumed | revoked
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // A code is unique within an organization (lookup key at the gate).
            $table->unique(['organization_id', 'code']);
            // Hot read: a host's own passes; an org's active passes.
            $table->index(['organization_id', 'host_id']);
            $table->index(['organization_id', 'status']);
            $table->index('host_id');
            $table->index('revoked_by');
        });

        Schema::create('gate_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Nullable: a denied scan of an unknown code has no pass to point at.
            $table->foreignUuid('pass_id')->nullable()->constrained('access_passes')->nullOnDelete();
            // The gate officer (or null for an automated/device entry).
            $table->foreignUuid('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gate')->default('Main Gate');
            $table->string('direction')->default('in');     // in | out
            $table->string('result')->default('granted');   // granted | denied
            $table->string('reason')->nullable();           // why a scan was denied
            $table->string('visitor_name')->nullable();
            $table->timestamp('recorded_at');
            // Append-only: an event log is never updated, only inserted.
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'recorded_at']);
            $table->index('pass_id');
            $table->index('officer_id');
        });

        // PostgreSQL: pin enum-like columns to their allowed values at the DB
        // layer so an invalid state can't exist even via raw SQL. Mirrors the
        // project's schema-integrity convention; driver-guarded for portability.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE access_passes ADD CONSTRAINT access_passes_type_check CHECK (type IN ('single','recurring','domestic'))");
            DB::statement("ALTER TABLE access_passes ADD CONSTRAINT access_passes_status_check CHECK (status IN ('active','consumed','revoked'))");
            DB::statement("ALTER TABLE gate_events ADD CONSTRAINT gate_events_direction_check CHECK (direction IN ('in','out'))");
            DB::statement("ALTER TABLE gate_events ADD CONSTRAINT gate_events_result_check CHECK (result IN ('granted','denied'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_events');
        Schema::dropIfExists('access_passes');
    }
};
