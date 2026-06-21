<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumer rewards: one account per user (cached balance + boost) plus an
 * append-only ledger of earn/redeem entries. User-scoped (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('points_balance')->default(0);
            $table->decimal('boost_multiplier', 4, 2)->default(1.0);
            $table->timestamp('boost_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reward_ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // earn | redeem
            $table->integer('points'); // signed: + earn, - redeem
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_ledger_entries');
        Schema::dropIfExists('reward_accounts');
    }
};
