<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM leads — the sales -> onboarding pipeline that ends by converting a won
 * lead into a live tenant (organization).
 *
 * PLATFORM-scoped (NOT organization-scoped): a lead exists BEFORE any tenant
 * does, so there is no organization_id FK. The tenant that a won lead becomes
 * is recorded in converted_organization_id on convert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // The organization a won lead was converted into (set on convert; nullable until then).
            $table->foreignUuid('converted_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            // The platform-staff user who created the lead (nullable for imported/automated leads).
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('client_type')->default('other'); // university | estate | company | other
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('region')->nullable();
            $table->string('source')->nullable();
            $table->string('stage')->default('new');          // new | qualified | quoted | won | lost
            $table->jsonb('quote')->nullable();               // a pricing snapshot attached during the quote step
            $table->text('notes')->nullable();
            $table->timestamps();

            // Hot reads: the pipeline filtered by stage and by client type.
            $table->index(['stage', 'client_type']);
            // FK indexes (Postgres does not auto-index foreign keys).
            $table->index('converted_organization_id');
            $table->index('created_by');
        });

        // PostgreSQL: pin enum-like columns to their allowed values at the DB
        // layer so an invalid state can't exist even via raw SQL. Mirrors the
        // project's schema-integrity convention; driver-guarded for portability.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_client_type_check CHECK (client_type IN ('university','estate','company','other'))");
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_stage_check CHECK (stage IN ('new','qualified','quoted','won','lost'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
