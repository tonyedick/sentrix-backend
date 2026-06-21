<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crowdsourced community alerts (Nearby Alerts / Confirm / Report / Verify).
 * User-scoped + geo-queried (ADR-0001 — no organization). Reuses the PostGIS
 * generated-geography + GiST pattern from Tracking/Responder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('impact')->default('moderate');
            $table->string('status')->default('active');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('confirmations_count')->default(0);
            $table->unsignedInteger('dismissals_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
        });

        Schema::create('community_alert_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind'); // confirm | dismiss
            $table->boolean('still_active')->default(true);
            $table->string('impact')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            // One vote per user per alert (updated in place on re-vote).
            $table->unique(['community_alert_id', 'user_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement(<<<'SQL'
            ALTER TABLE community_alerts ADD COLUMN location geography(Point, 4326)
                GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography) STORED
        SQL);
        DB::statement('CREATE INDEX community_alerts_location_gist ON community_alerts USING gist (location)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS community_alerts_location_gist');
            DB::statement('ALTER TABLE community_alerts DROP COLUMN IF EXISTS location');
        }

        Schema::dropIfExists('community_alert_confirmations');
        Schema::dropIfExists('community_alerts');
    }
};
