<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal trip-planning data (the "where are you going" screen): a user's saved
 * places (Home / Work / custom) and their recent destination searches.
 * User-scoped (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('kind')->default('other'); // home | work | other
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('recent_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('searched_at');
            $table->timestamps();

            $table->index(['user_id', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recent_searches');
        Schema::dropIfExists('saved_locations');
    }
};
