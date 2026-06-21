<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, assignment-scoped operational timeline (separate from the
 * cross-cutting audit_logs). Rendered on the incident console.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('type');
            $table->uuid('actor_id')->nullable();
            $table->uuid('assignment_responder_id')->nullable();
            $table->jsonb('payload')->nullable();

            // Append-only: creation time only, no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['assignment_id', 'created_at']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_events');
    }
};
