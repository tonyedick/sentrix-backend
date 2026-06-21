<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sentrix Responder Command — the NATIONAL AGENCY layer.
 *
 * Three tables:
 *  - agencies          : national responder agencies (Police, Fire, FRSC, …),
 *    each declaring the emergency categories it leads.
 *  - commands          : a 4-tier command hierarchy (national -> state -> zonal
 *    -> divisional) under an agency, with optional GPS coordinates for routing.
 *  - command_incidents : a parallel tracking envelope for an emergency routed to
 *    a lead command — NOT a mutation of the Incident domain's own lifecycle.
 *
 * PLATFORM-scoped (NOT organization-scoped): agencies and commands are national/
 * cross-tenant. command_incidents carry no organization_id — they are the
 * responder side of the alert fabric, not a tenant record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();                 // e.g. NPF, FRSC, FFS
            $table->string('name');
            $table->string('country', 4)->default('NG');      // ISO-ish country code
            $table->jsonb('categories')->default('[]');       // crime|fire|traffic|medical|disaster|civil
            $table->string('hotline')->nullable();
            $table->string('color')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('status')->default('active');      // active | inactive
            $table->timestamps();

            $table->index('country');
            $table->index('status');
        });

        Schema::create('commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Self-referential parent (national nodes have no parent). Declared as
            // a plain column here; the FK is added in a separate Schema::table()
            // below so the table's primary key exists before the self-FK is
            // created (Postgres rejects a self-FK added in the same CREATE).
            $table->uuid('parent_id')->nullable();
            $table->string('tier');                           // national | state | zonal | divisional
            $table->string('name');
            $table->string('area')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('parent_id');
            $table->index(['agency_id', 'tier']);
        });

        // Self-referencing FK added after the table (and its PK) exist.
        Schema::table('commands', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('commands')->nullOnDelete();
        });

        Schema::create('command_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // The lead command this envelope was routed to, and its agency.
            $table->foreignUuid('command_id')->constrained('commands')->cascadeOnDelete();
            $table->foreignUuid('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('category');                       // crime|fire|traffic|medical|disaster|civil
            $table->string('severity');                       // critical|high|medium|low
            $table->string('status')->default('new');         // new|acknowledged|en_route|on_scene|resolved|stood_down
            $table->string('source_type');                    // alert|emergency|sos|manual
            $table->string('source_ref')->nullable();
            $table->string('summary');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('sla_dispatch_due_at')->nullable();
            $table->timestamp('sla_onscene_due_at')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('command_id');
            $table->index('agency_id');
            $table->index('status');
            $table->index('category');
            $table->index('severity');
        });

        // Pin enum-like columns at the DB layer (Postgres only; driver-guarded).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE agencies ADD CONSTRAINT agencies_status_check CHECK (status IN ('active','inactive'))");

            DB::statement("ALTER TABLE commands ADD CONSTRAINT commands_tier_check CHECK (tier IN ('national','state','zonal','divisional'))");

            DB::statement("ALTER TABLE command_incidents ADD CONSTRAINT command_incidents_category_check CHECK (category IN ('crime','fire','traffic','medical','disaster','civil'))");
            DB::statement("ALTER TABLE command_incidents ADD CONSTRAINT command_incidents_severity_check CHECK (severity IN ('critical','high','medium','low'))");
            DB::statement("ALTER TABLE command_incidents ADD CONSTRAINT command_incidents_status_check CHECK (status IN ('new','acknowledged','en_route','on_scene','resolved','stood_down'))");
            DB::statement("ALTER TABLE command_incidents ADD CONSTRAINT command_incidents_source_type_check CHECK (source_type IN ('alert','emergency','sos','manual'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE command_incidents DROP CONSTRAINT IF EXISTS command_incidents_category_check');
            DB::statement('ALTER TABLE command_incidents DROP CONSTRAINT IF EXISTS command_incidents_severity_check');
            DB::statement('ALTER TABLE command_incidents DROP CONSTRAINT IF EXISTS command_incidents_status_check');
            DB::statement('ALTER TABLE command_incidents DROP CONSTRAINT IF EXISTS command_incidents_source_type_check');
            DB::statement('ALTER TABLE commands DROP CONSTRAINT IF EXISTS commands_tier_check');
            DB::statement('ALTER TABLE agencies DROP CONSTRAINT IF EXISTS agencies_status_check');
        }

        Schema::dropIfExists('command_incidents');
        Schema::dropIfExists('commands');
        Schema::dropIfExists('agencies');
    }
};
