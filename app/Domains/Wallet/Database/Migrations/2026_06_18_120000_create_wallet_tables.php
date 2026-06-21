<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe Rides — Wallet & payments. Consumer-scoped (ADR-0001): one wallet per
 * user, an append-only transaction log, the user's payment methods, and
 * referral claims. ALL MONEY IS INTEGER CENTS — never floats.
 *
 * The unique `reference` on wallet_transactions is the idempotency guard for
 * top-up confirmation (a PSP webhook confirms in production). Enum-ish columns
 * are pinned with driver-guarded Postgres CHECK constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // One wallet per user.
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('lifetime_topup_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type'); // topup | charge | payout | referral_credit | refund
            $table->string('direction'); // credit | debit
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('balance_after_cents');
            $table->string('method')->nullable(); // transfer | ussd | card | bank | wallet | system
            // Idempotency key — unique where present (e.g. the top-up reference).
            $table->string('reference')->nullable()->unique();
            $table->string('status')->default('completed'); // pending | completed | failed
            $table->string('description')->nullable();
            // Append-only: created_at only (model sets UPDATED_AT = null).
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind'); // cash | card | wallet
            $table->string('label')->nullable(); // e.g. 'Visa ****4242'
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable(); // last 4 ONLY — never a PAN
            $table->boolean('is_default')->default(false);
            $table->boolean('removable')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });

        Schema::create('referral_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code'); // the referrer's (derived) code
            $table->foreignUuid('referrer_id')->constrained('users')->cascadeOnDelete();
            // A user may claim exactly once.
            $table->foreignUuid('claimer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('claimed_at');

            $table->index('referrer_id');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_type_check CHECK (type IN ('topup','charge','payout','referral_credit','refund'))");
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_direction_check CHECK (direction IN ('credit','debit'))");
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_method_check CHECK (method IS NULL OR method IN ('transfer','ussd','card','bank','wallet','system'))");
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_status_check CHECK (status IN ('pending','completed','failed'))");
            DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_kind_check CHECK (kind IN ('cash','card','wallet'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_claims');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
