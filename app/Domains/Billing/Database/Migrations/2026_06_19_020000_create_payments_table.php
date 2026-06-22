<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PSP checkout payments. A pending Payment is created at checkout, then marked
 * paid by either the signed webhook (charge.success) or the sandbox simulate
 * endpoint, which also activates/extends the user's Subscription. Money is
 * integer minor units (cents) — never floats. User-scoped (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('plan_key');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status')->default('pending'); // pending | paid | failed
            $table->string('provider')->nullable();
            $table->string('region')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Schema-integrity: pin the status enum on Postgres (driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'paid', 'failed'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
