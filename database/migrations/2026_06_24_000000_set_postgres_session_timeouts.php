<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Robustness guardrails at the database role level (PostgreSQL only):
 *
 *  - statement_timeout: caps any single query so a pathological one (e.g. a
 *    sequential scan over a huge table, or an accidental cartesian join) is
 *    aborted instead of pinning a connection indefinitely.
 *  - idle_in_transaction_session_timeout: aborts transactions left open by a
 *    stuck client, releasing the locks/connection they hold.
 *
 * Applied with ALTER ROLE CURRENT_USER (a USERSET GUC a role may set on itself,
 * so no superuser needed) which makes it a per-backend default — the correct
 * place when running behind PgBouncer in transaction-pooling mode, where
 * per-session `SET` would leak across pooled clients.
 *
 * The values are deliberately generous (30s/60s) so normal API requests and
 * queue jobs are never affected; only runaway queries hit the cap. Long
 * maintenance work (large CREATE INDEX, backfills) should run `SET
 * statement_timeout = 0` for its own session — and the migrations that build big
 * indexes already do exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $statement = $this->sanitizeDuration((string) env('DB_STATEMENT_TIMEOUT', '30s'), '30s');
        $idle = $this->sanitizeDuration((string) env('DB_IDLE_TX_TIMEOUT', '60s'), '60s');

        try {
            DB::statement("ALTER ROLE CURRENT_USER SET statement_timeout = '{$statement}'");
            DB::statement("ALTER ROLE CURRENT_USER SET idle_in_transaction_session_timeout = '{$idle}'");
        } catch (\Throwable $e) {
            // Non-fatal: a fully managed role may lack ALTER privilege. In that
            // case set these defaults from your database provider's console
            // (ALTER ROLE / parameter group) — the app does not depend on them.
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('ALTER ROLE CURRENT_USER RESET statement_timeout');
            DB::statement('ALTER ROLE CURRENT_USER RESET idle_in_transaction_session_timeout');
        } catch (\Throwable $e) {
            // ignore — see up()
        }
    }

    /**
     * Allow only digits + a time unit (e.g. "30s", "30000", "1min") to keep the
     * env-sourced value out of harm's way before it is inlined into SQL.
     */
    private function sanitizeDuration(string $value, string $fallback): string
    {
        $clean = preg_replace('/[^0-9a-z]/i', '', $value) ?? '';

        return $clean === '' ? $fallback : $clean;
    }
};
