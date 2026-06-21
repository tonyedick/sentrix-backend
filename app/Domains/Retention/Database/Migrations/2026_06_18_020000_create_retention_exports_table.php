<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retention: archive export manifests.
 *
 * Each row records ONE "archive-first" export of a set of Evidence observations
 * — the durable, downloadable manifest produced before those observations are
 * sealed (and later purged). The manifest jsonb holds a flat list of
 * {id, kind, plate, observed_at, snapshot_url, clip_url} entries so the bundle
 * stays self-describing even after the source rows are purged. Append-only:
 * an export is an immutable historical record, so there is no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('exported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('format')->default('json'); // json
            $table->unsignedInteger('count')->default(0);

            // Self-describing bundle: list of {id, kind, plate, observed_at,
            // snapshot_url, clip_url}. jsonb so it indexes/queries on Postgres.
            $table->jsonb('manifest')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'created_at']);
            $table->index('exported_by');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retention_exports ADD CONSTRAINT retention_exports_format_check CHECK (format IN ('json'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_exports');
    }
};
