<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard notifications table, adapted for UUID notifiables (users).
 * Backs the `database` channel (in-app notification feed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Unread-feed lookups per recipient.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_recipient_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
