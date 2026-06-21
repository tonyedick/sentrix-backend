<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insurance — the synergy layer between security posture and risk pricing.
 *
 * Insurers price on risk; Sentrix measures it. A better security posture lowers
 * the premium, and claims are backed by Sentrix evidence. This migration owns the
 * two persisted entities of the domain:
 *
 *   - insurance_policies: a risk-priced coverage agreement for an organization.
 *   - insurance_claims:   a claim filed against a policy, with an approve/reject
 *                         decision trail.
 *
 * The risk profile and quote are computed read-only from sibling-domain signals
 * and are not persisted here.
 *
 * Organization-scoped throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            // The member who created the policy (nullable for imported/automated creation).
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('draft');     // draft | active | lapsed | cancelled
            $table->unsignedBigInteger('premium_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->jsonb('coverage')->nullable();           // structured coverage terms
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();

            // Hot reads: an org's policies by status, plus FK index.
            $table->index(['organization_id', 'status']);
            $table->index('created_by');
        });

        Schema::create('insurance_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('insurance_policies')->cascadeOnDelete();
            // The member who filed the claim.
            $table->foreignUuid('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('filed');      // filed | approved | rejected | paid
            $table->text('description')->nullable();
            // The member (insurer/admin) who decided the claim.
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Hot reads: an org's claims by status, claims for a policy, plus FK indexes.
            $table->index(['organization_id', 'status']);
            $table->index('policy_id');
            $table->index('filed_by');
            $table->index('decided_by');
        });

        // PostgreSQL: pin enum-like columns to their allowed values at the DB
        // layer so an invalid state can't exist even via raw SQL. Mirrors the
        // project's schema-integrity convention; driver-guarded for portability.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE insurance_policies ADD CONSTRAINT insurance_policies_status_check CHECK (status IN ('draft','active','lapsed','cancelled'))");
            DB::statement("ALTER TABLE insurance_claims ADD CONSTRAINT insurance_claims_status_check CHECK (status IN ('filed','approved','rejected','paid'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('insurance_policies');
    }
};
