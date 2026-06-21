<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sentrix Responder Command — Computer-Aided Dispatch (CAD) slice.
 *
 * Builds ON TOP OF the Command domain (agencies, commands, command_incidents).
 * Timestamp sorts AFTER 2026_06_18_000300 so FKs to commands resolve.
 *
 * Three tables:
 *  - units          : first-class FIELD UNITS with live status + location (AVL),
 *    belonging to a command and (denormalized) its agency.
 *  - unit_dispatches: the assignment record created when a unit is dispatched to
 *    a command incident (Omni's assignUnit "creates an assignment record").
 *  - bolos          : BOLO / officer-safety broadcasts issued down a command.
 *
 * PLATFORM-scoped (NOT organization-scoped): the national agency/command layer is
 * cross-tenant — these carry no organization_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('command_id')->constrained('commands')->cascadeOnDelete();
            // Denormalized agency for fast agency filtering / closest-unit queries.
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('call_sign');
            $table->string('kind')->default('patrol'); // patrol|armed_response|k9|traffic|ambulance|fire_engine|rescue|marine|air|bomb|command
            $table->jsonb('capabilities')->default('[]');
            $table->unsignedInteger('crew')->default(1);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('area')->nullable();
            $table->string('status')->default('available'); // available|assigned|en_route|on_scene|unavailable|out_of_service
            // The incident this unit is committed to. Plain uuid column (NO
            // cross-table FK to command_incidents) to avoid migration-order /
            // coupling issues; just indexed.
            $table->uuid('assigned_incident_id')->nullable();
            $table->timestamps();

            $table->index('command_id');
            $table->index('agency_id');
            $table->index('assigned_incident_id');
            $table->index(['command_id', 'status']);
            $table->index(['agency_id', 'status']);
        });

        Schema::create('unit_dispatches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('command_incident_id')->constrained('command_incidents')->cascadeOnDelete();
            $table->uuid('dispatched_by')->nullable();
            $table->timestamp('dispatched_at');
            $table->timestamp('cleared_at')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamps();

            $table->index('unit_id');
            $table->index('command_incident_id');
        });

        Schema::create('bolos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignUuid('command_id')->constrained('commands')->cascadeOnDelete();
            $table->string('kind')->default('general'); // vehicle|person|officer_safety|general
            $table->string('subject');
            $table->jsonb('details')->nullable();
            $table->string('status')->default('active'); // active|cleared
            $table->uuid('issued_by')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['command_id', 'status']);
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE units ADD CONSTRAINT units_kind_check CHECK (kind IN ('patrol','armed_response','k9','traffic','ambulance','fire_engine','rescue','marine','air','bomb','command'))");
            DB::statement("ALTER TABLE units ADD CONSTRAINT units_status_check CHECK (status IN ('available','assigned','en_route','on_scene','unavailable','out_of_service'))");

            DB::statement("ALTER TABLE bolos ADD CONSTRAINT bolos_kind_check CHECK (kind IN ('vehicle','person','officer_safety','general'))");
            DB::statement("ALTER TABLE bolos ADD CONSTRAINT bolos_status_check CHECK (status IN ('active','cleared'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bolos DROP CONSTRAINT IF EXISTS bolos_status_check');
            DB::statement('ALTER TABLE bolos DROP CONSTRAINT IF EXISTS bolos_kind_check');
            DB::statement('ALTER TABLE units DROP CONSTRAINT IF EXISTS units_status_check');
            DB::statement('ALTER TABLE units DROP CONSTRAINT IF EXISTS units_kind_check');
        }

        Schema::dropIfExists('bolos');
        Schema::dropIfExists('unit_dispatches');
        Schema::dropIfExists('units');
    }
};
