<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound partner-integration registry. `webhooks` holds an organization's
 * subscribed endpoints (URL + subscribed event keys + signing secret);
 * `webhook_deliveries` is an append-and-update observability ledger of each
 * delivery attempt (status, success, error, attempts, when delivered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('url');
            // List of subscribed event keys, e.g. ["incident.opened","emergency.triggered"].
            $table->jsonb('events');
            // Random secret generated on create; used to HMAC-sign deliveries.
            $table->string('secret');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();

            $table->timestamps();

            // Delivery fan-out filters by organization + active.
            $table->index(['organization_id', 'active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('webhook_id')->constrained('webhooks')->cascadeOnDelete();

            $table->string('event');
            $table->jsonb('payload');
            $table->string('signature');
            $table->unsignedInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();

            // Append-and-update ledger: keep created_at only.
            $table->timestamp('created_at')->nullable();

            // Recent-attempts queries read by webhook, newest first.
            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
