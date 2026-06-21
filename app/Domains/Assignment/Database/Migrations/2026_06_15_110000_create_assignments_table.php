<?php

declare(strict_types=1);

use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The incident-scoped coordination record (Assignment aggregate). At most one
 * active assignment per incident (partial unique index); reassignment and
 * re-dispatch happen within it via assignment_responders lines.
 *
 * `primary_assignment_responder_id` is a denormalised pointer; its FK is added
 * in the rename migration once assignment_responders exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('incident_id')->constrained('incidents')->cascadeOnDelete();

            $table->string('status')->default(AssignmentStatus::Pending->value);
            $table->string('dispatch_mode')->default('manual');
            $table->boolean('required_primary')->default(true);
            $table->unsignedInteger('required_supporting')->default(0);

            $table->uuid('primary_assignment_responder_id')->nullable();

            $table->foreignUuid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acceptance_deadline_at')->nullable();
            $table->unsignedInteger('escalation_level')->default(0);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('opened_by');
            $table->index('primary_assignment_responder_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $statuses = collect(AssignmentStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_dispatch_mode_check CHECK (dispatch_mode IN ('manual', 'auto'))");

        // One active assignment per incident.
        DB::statement("CREATE UNIQUE INDEX assignments_one_active_per_incident ON assignments (incident_id) WHERE status NOT IN ('completed', 'cancelled')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS assignments_one_active_per_incident');
            DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_dispatch_mode_check');
            DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_status_check');
        }

        Schema::dropIfExists('assignments');
    }
};
