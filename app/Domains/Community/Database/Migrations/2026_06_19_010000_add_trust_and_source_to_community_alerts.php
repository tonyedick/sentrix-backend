<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive trust-weighting + provenance for community alerts (sorts after the
 * 2026_06_16 base tables and the 2026_06_19_000200 watermark). Backward
 * compatible: existing rows default to source='community' and confidence=0.
 *
 *  - source: who published the alert (community | official | ai). Official/AI
 *    are staff/Core-published and arrive verified.
 *  - confidence: a signed trust-weighted tally (verify adds weight, dispute
 *    subtracts) that flips status unverified <-> active <-> resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_alerts', function (Blueprint $table): void {
            $table->string('source')->default('community')->after('status');
            $table->integer('confidence')->default(0)->after('dismissals_count');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE community_alerts
                ADD CONSTRAINT community_alerts_source_check
                CHECK (source IN ('community', 'official', 'ai'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_alerts DROP CONSTRAINT IF EXISTS community_alerts_source_check');
        }

        Schema::table('community_alerts', function (Blueprint $table): void {
            $table->dropColumn(['source', 'confidence']);
        });
    }
};
