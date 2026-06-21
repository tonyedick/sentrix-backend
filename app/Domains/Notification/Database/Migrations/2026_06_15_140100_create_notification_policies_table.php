<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization notification policy: which channels are enabled. One row per
 * organization; the resolver falls back to sentrix.notifications.channels when an
 * organization has no row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            // Enabled channels (friendly names): mail, database, broadcast, sms, push.
            $table->jsonb('channels');

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_policies');
    }
};
