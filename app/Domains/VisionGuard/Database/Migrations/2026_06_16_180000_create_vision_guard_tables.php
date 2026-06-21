<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vision Guard: a user's connected camera sources and the media they capture.
 * User-scoped (ADR-0001). Media bytes live in object storage (via the
 * MediaStorage abstraction); these rows hold the metadata + storage key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('status')->default('active'); // active | inactive
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('camera_source_id')->nullable()->constrained('camera_sources')->nullOnDelete();
            $table->string('storage_key');
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status')->default('pending'); // pending | uploaded
            $table->uuid('trip_id')->nullable();
            $table->uuid('emergency_id')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('camera_sources');
    }
};
