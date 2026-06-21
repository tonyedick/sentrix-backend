<?php

declare(strict_types=1);

use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The dispatch record linking a responder to an incident OR an emergency, with
 * the full accept → en-route → on-scene → complete lifecycle and timestamps.
 *
 * Integrity is enforced at the database:
 *  - a CHECK that an assignment targets at least one of incident/emergency;
 *  - a CHECK pinning status to the enum;
 *  - a PARTIAL UNIQUE index so a responder holds at most one active assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('responder_id')->constrained('responders')->cascadeOnDelete();
            $table->foreignUuid('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignUuid('emergency_id')->nullable()->constrained('emergencies')->nullOnDelete();

            $table->string('status')->default(AssignmentResponderStatus::Offered->value);
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('on_scene_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('outcome')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('incident_id');
            $table->index('emergency_id');
            $table->index('responder_id');
            $table->index('assigned_by');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = collect(AssignmentResponderStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("ALTER TABLE responder_assignments ADD CONSTRAINT responder_assignments_status_check CHECK (status IN ({$allowed}))");

        DB::statement('ALTER TABLE responder_assignments ADD CONSTRAINT responder_assignments_target_check CHECK (incident_id IS NOT NULL OR emergency_id IS NOT NULL)');

        $active = collect(AssignmentResponderStatus::activeValues())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("CREATE UNIQUE INDEX responder_assignments_one_active ON responder_assignments (responder_id) WHERE status IN ({$active})");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS responder_assignments_one_active');
            DB::statement('ALTER TABLE responder_assignments DROP CONSTRAINT IF EXISTS responder_assignments_target_check');
            DB::statement('ALTER TABLE responder_assignments DROP CONSTRAINT IF EXISTS responder_assignments_status_check');
        }

        Schema::dropIfExists('responder_assignments');
    }
};
