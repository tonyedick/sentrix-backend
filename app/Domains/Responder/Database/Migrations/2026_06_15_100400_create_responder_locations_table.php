<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only responder position fixes (mirrors trip_locations). Written in
 * idempotent batches via insertOrIgnore on (responder_id, client_fix_id), and
 * queried for history and the live map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responder_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('responder_id')->constrained('responders')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('client_fix_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();

            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            // Append-only: created_at is the receive time; no updated_at.
            $table->timestamp('created_at')->nullable();

            // Idempotent ingest: a resent fix is dropped by this unique key.
            $table->unique(['responder_id', 'client_fix_id']);
            $table->index(['responder_id', 'recorded_at']);
            $table->index(['organization_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responder_locations');
    }
};
