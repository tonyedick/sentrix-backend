<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sentrix Ledger — the ecosystem write-spine.
 *
 * Two tables:
 *  - ledger_sources : the registry of data SOURCES (each Sentrix product/feed),
 *    with an onboarding lifecycle (pending -> active <-> suspended -> revoked),
 *    a hashed ingest key, and lifetime write counters + a dead-man flag.
 *  - ledger_writes  : an append-only feed of the WRITES sources report.
 *
 * PLATFORM-scoped (NOT organization-scoped). organization_id is a nullable,
 * un-constrained tenant tag (cross-tenant by design — a write may belong to any
 * org, and a source may be platform-wide), so there is deliberately no FK on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('product')->nullable();
            $table->string('kind')->default('service');       // product | service | device | integration
            // Cross-tenant tag, NOT a tenant FK: a source may be platform-wide.
            $table->uuid('organization_id')->nullable();
            $table->string('status')->default('pending');     // pending | active | suspended | revoked
            // Hashed ingest key — the raw key is shown once at creation/rotation and never persisted.
            $table->string('key_hash');
            $table->timestamp('last_write_at')->nullable();
            $table->unsignedBigInteger('write_count')->default(0);
            // Dead-man switch: set true once a stale source is flagged, re-armed by the next write.
            $table->boolean('stale_alerted')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Hot reads: active sources for health/stale sweeps, and key lookup on ingest.
            $table->index(['status', 'last_write_at']);
            $table->index('organization_id');
            $table->index('key_hash');
        });

        Schema::create('ledger_writes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ledger_source_id')->constrained('ledger_sources')->cascadeOnDelete();
            $table->string('type');
            $table->string('summary')->nullable();
            $table->string('ref')->nullable();
            // Cross-tenant tag carried from the reporting product (no FK).
            $table->uuid('organization_id')->nullable();
            $table->timestamp('recorded_at');
            // Append-only: insert time only, no updated_at.
            $table->timestamp('created_at')->nullable();

            // Hot path: a source's feed in reverse chronological order (paginated).
            $table->index(['ledger_source_id', 'recorded_at']);
            // Feed filters by type and by org.
            $table->index(['type', 'recorded_at']);
            $table->index(['organization_id', 'recorded_at']);
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ledger_sources ADD CONSTRAINT ledger_sources_kind_check CHECK (kind IN ('product','service','device','integration'))");
            DB::statement("ALTER TABLE ledger_sources ADD CONSTRAINT ledger_sources_status_check CHECK (status IN ('pending','active','suspended','revoked'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ledger_sources DROP CONSTRAINT IF EXISTS ledger_sources_kind_check');
            DB::statement('ALTER TABLE ledger_sources DROP CONSTRAINT IF EXISTS ledger_sources_status_check');
        }

        Schema::dropIfExists('ledger_writes');
        Schema::dropIfExists('ledger_sources');
    }
};
