<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization escalation policy: configurable thresholds and on/off toggles
 * for each escalation type. One row per organization; the resolver falls back to
 * the sentrix.escalation.* config defaults when an organization has no row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            // Thresholds (seconds).
            $table->unsignedInteger('incident_unassigned_seconds')->default(300);
            $table->unsignedInteger('assignment_unaccepted_seconds')->default(120);
            $table->unsignedInteger('responder_no_progression_seconds')->default(600);

            // Toggles.
            $table->boolean('incident_escalation_enabled')->default(true);
            $table->boolean('assignment_escalation_enabled')->default(true);
            $table->boolean('responder_escalation_enabled')->default(true);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_policies');
    }
};
