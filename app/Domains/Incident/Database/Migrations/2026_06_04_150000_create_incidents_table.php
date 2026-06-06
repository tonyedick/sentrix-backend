<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Optional originating emergency.
            $table->foreignUuid('emergency_id')->nullable()->index()->constrained('emergencies')->nullOnDelete();

            $table->foreignUuid('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('open');
            $table->string('severity')->default('medium');

            $table->string('title');
            $table->text('summary')->nullable();

            $table->timestamp('opened_at');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Incident queues filter by organization + status.
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
