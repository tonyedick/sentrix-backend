<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // The monitored individual the trip belongs to.
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status')->default('active');

            $table->string('origin_label')->nullable();
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();

            $table->string('destination_label')->nullable();
            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Hot paths: per-organization status boards and overdue sweeps.
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expected_arrival_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
