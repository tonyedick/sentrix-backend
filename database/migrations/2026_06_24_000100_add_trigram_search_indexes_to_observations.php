<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make Evidence search index-backed (PostgreSQL only).
 *
 * The vault search uses substring/leading-wildcard matching:
 *   - label   : `label ILIKE '%term%'`
 *   - plate   : `replace(replace(plate,'-',''),' ','') LIKE '%NEEDLE%'`  (the
 *               column is stored upper-cased and the needle is normalised the
 *               same way in EvidenceController::applyFacets)
 *
 * A plain b-tree can't serve a leading `%` wildcard, so these were sequential
 * scans. pg_trgm GIN indexes turn both into index lookups. The plate index is a
 * FUNCTIONAL index on the exact normalisation expression the query uses, so the
 * planner can match it — keep the two expressions identical if either changes.
 *
 * Built without CONCURRENTLY (migrations run in a transaction). On an existing
 * very large table, create these manually with CREATE INDEX CONCURRENTLY instead
 * to avoid a long write-lock; see SENTRIX_DB_SCALABILITY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Don't let the role-level statement_timeout abort a large index build.
        DB::statement('SET statement_timeout = 0');

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(
            'CREATE INDEX IF NOT EXISTS observations_label_trgm '
            .'ON observations USING gin (label gin_trgm_ops)'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS observations_plate_norm_trgm '
            ."ON observations USING gin ((replace(replace(plate, '-', ''), ' ', '')) gin_trgm_ops)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS observations_label_trgm');
        DB::statement('DROP INDEX IF EXISTS observations_plate_norm_trgm');
    }
};
