<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make incidents openable by a MACHINE (a camera/product detection or a
 * SafeSignal), which has no human opener.
 *
 *   (a) opened_by becomes NULLABLE — a detection-opened incident has no user.
 *   (b) a new `origin` column records who opened it: human | detection | signal
 *       | manual, defaulting to 'human'.
 *
 * Backward-compatible by construction: every existing code path passes
 * opened_by and gets the default origin 'human', so prior data + tests are
 * unaffected. The detection pipeline (Ingest) is the only writer of NULL
 * opened_by / non-'human' origin.
 *
 * Postgres-safe: opened_by's NOT NULL is dropped via a driver-guarded raw
 * statement (avoids depending on doctrine/dbal's ->change() introspection for a
 * column that participates in a foreign key); origin's CHECK is pgsql-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (b) Add the origin column (portable across drivers).
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('origin')->default('human')->after('opened_by');
        });

        // (a) Drop opened_by's NOT NULL. Driver-guarded raw DDL keeps this
        // deterministic on Postgres (the production driver) and a no-op on
        // SQLite, which has no NOT NULL on an existing nullable-by-omission
        // column path we rely on here — but to be safe across the test sqlite
        // driver too, fall back to a dbal ->change().
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE incidents ALTER COLUMN opened_by DROP NOT NULL');

            DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_origin_check CHECK (origin IN ('human','detection','signal','manual'))");
        } else {
            // SQLite (tests) and others: use the schema builder change().
            Schema::table('incidents', function (Blueprint $table): void {
                $table->uuid('opened_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_origin_check');
            // Best-effort restore of NOT NULL (only valid if no NULL rows remain).
            DB::statement('ALTER TABLE incidents ALTER COLUMN opened_by SET NOT NULL');
        } else {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->uuid('opened_by')->nullable(false)->change();
            });
        }

        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
