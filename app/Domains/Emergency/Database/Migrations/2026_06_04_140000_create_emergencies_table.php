<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // The person the emergency concerns (usually the one who triggered it).
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Optional originating trip.
            $table->foreignUuid('trip_id')->nullable()->index()->constrained('trips')->nullOnDelete();

            $table->string('status')->default('triggered');
            $table->string('severity')->default('high');

            $table->text('message')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Live emergency boards filter by organization + status.
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergencies');
    }
};
