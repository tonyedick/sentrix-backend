<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel delivery ledger. One row per (notification, channel): records the
 * attempt count, terminal status (pending/sent/failed), the last error, and when
 * it was sent. Populated by RecordNotificationDelivery from the framework's
 * NotificationSending/Sent/Failed events, so it captures every channel uniformly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The Laravel notification instance id (stable across attempts + channels).
            $table->uuid('notification_id');
            $table->string('notification_type');
            $table->string('channel');

            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('notifiable_type')->nullable();
            $table->uuid('notifiable_id')->nullable();

            $table->string('status')->default('pending'); // pending | sent | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['notification_id', 'channel']);
            $table->index(['organization_id', 'status']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
