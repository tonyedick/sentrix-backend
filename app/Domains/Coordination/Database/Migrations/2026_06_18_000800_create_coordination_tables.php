<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sentrix Responder Command — Coordination slice (the final part of the Security
 * Command / CAD area).
 *
 * Builds ON TOP OF the Command domain (agencies, commands, command_incidents) and
 * the Cad domain (units). Timestamp sorts AFTER 2026_06_18_000600 so FKs to
 * commands / command_incidents / units resolve.
 *
 * Four clusters, four tables (analytics is a pure computed endpoint — no table):
 *  - mutual_aid_requests : inter-agency assistance requests (mutualaid.js).
 *  - unit_messages       : CAD-to-radio / MDT thread per unit (unitcomms.js).
 *  - taskings            : HQ duty taskings, SENT->ACKNOWLEDGED->RESOLVED.
 *  - duty_entries        : append-only sign-in book per control room.
 *
 * PLATFORM-scoped (NOT organization-scoped): the national agency/command layer is
 * cross-tenant — these carry no organization_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutual_aid_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('command_incident_id')->constrained('command_incidents')->cascadeOnDelete();
            $table->foreignUuid('requesting_command_id')->constrained('commands')->cascadeOnDelete();
            $table->foreignUuid('responding_command_id')->constrained('commands')->cascadeOnDelete();
            $table->string('status')->default('requested'); // requested|accepted|declined|cancelled
            $table->text('message')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('command_incident_id');
            $table->index('requesting_command_id');
            $table->index('responding_command_id');
            $table->index(['command_incident_id', 'status']);
            $table->index(['responding_command_id', 'status']);
        });

        Schema::create('unit_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            // The incident this message relates to. Plain uuid column (NO cross-
            // table FK to command_incidents) — just indexed — to mirror the CAD
            // domain's assigned_incident_id decoupling.
            $table->uuid('command_incident_id')->nullable();
            $table->string('direction')->default('dispatch_to_unit'); // dispatch_to_unit|unit_to_dispatch
            $table->text('body');
            $table->uuid('sender')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('unit_id');
            $table->index('command_incident_id');
            $table->index(['unit_id', 'created_at']);
        });

        Schema::create('taskings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind')->default('general'); // incident|sos|detection|general
            $table->string('ref')->nullable();
            $table->string('title');
            $table->uuid('assignee')->nullable();
            $table->string('status')->default('sent'); // sent|acknowledged|resolved
            $table->uuid('created_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('kind');
            $table->index('assignee');
            $table->index(['status', 'kind']);
        });

        // Append-only duty book: no updated_at (the model sets UPDATED_AT = null).
        Schema::create('duty_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope_type'); // center|client|command
            $table->string('scope_id');
            $table->string('person_name');
            $table->string('role')->nullable();
            $table->string('action'); // sign_in|sign_out|handover
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['scope_type', 'scope_id']);
            $table->index('recorded_at');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE mutual_aid_requests ADD CONSTRAINT mutual_aid_requests_status_check CHECK (status IN ('requested','accepted','declined','cancelled'))");

            DB::statement("ALTER TABLE unit_messages ADD CONSTRAINT unit_messages_direction_check CHECK (direction IN ('dispatch_to_unit','unit_to_dispatch'))");

            DB::statement("ALTER TABLE taskings ADD CONSTRAINT taskings_kind_check CHECK (kind IN ('incident','sos','detection','general'))");
            DB::statement("ALTER TABLE taskings ADD CONSTRAINT taskings_status_check CHECK (status IN ('sent','acknowledged','resolved'))");

            DB::statement("ALTER TABLE duty_entries ADD CONSTRAINT duty_entries_scope_type_check CHECK (scope_type IN ('center','client','command'))");
            DB::statement("ALTER TABLE duty_entries ADD CONSTRAINT duty_entries_action_check CHECK (action IN ('sign_in','sign_out','handover'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE duty_entries DROP CONSTRAINT IF EXISTS duty_entries_action_check');
            DB::statement('ALTER TABLE duty_entries DROP CONSTRAINT IF EXISTS duty_entries_scope_type_check');
            DB::statement('ALTER TABLE taskings DROP CONSTRAINT IF EXISTS taskings_status_check');
            DB::statement('ALTER TABLE taskings DROP CONSTRAINT IF EXISTS taskings_kind_check');
            DB::statement('ALTER TABLE unit_messages DROP CONSTRAINT IF EXISTS unit_messages_direction_check');
            DB::statement('ALTER TABLE mutual_aid_requests DROP CONSTRAINT IF EXISTS mutual_aid_requests_status_check');
        }

        Schema::dropIfExists('duty_entries');
        Schema::dropIfExists('taskings');
        Schema::dropIfExists('unit_messages');
        Schema::dropIfExists('mutual_aid_requests');
    }
};
