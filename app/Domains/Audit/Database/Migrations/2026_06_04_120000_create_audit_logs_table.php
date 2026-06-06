<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail. Every safety-critical operational event records an
 * immutable row here (action, actor, tenant, subject, metadata). Rows are never
 * updated — hence created_at only, no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Tenant scope. Nullable: some events (e.g. registration) are not yet
            // org-scoped. Indexed for per-organization audit queries.
            $table->foreignUuid('organization_id')->nullable()->index()->constrained('organizations')->nullOnDelete();

            // The actor who caused the event, if any (system/console actions are null).
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();

            // Dotted action key, e.g. "emergency.triggered", "member.joined".
            $table->string('action')->index();

            // Polymorphic subject the action concerns (a Trip, Emergency, etc.).
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_id')->nullable();
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');

            // Flexible JSONB payload (state transitions, severity, etc.).
            $table->jsonb('metadata')->nullable();

            // Request provenance.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
