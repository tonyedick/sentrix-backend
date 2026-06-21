<?php

declare(strict_types=1);

use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes the Responder-domain `responder_assignments` table to the Assignment
 * domain's `assignment_responders` line table: renames it, parents it to an
 * Assignment, and adds the role. Postgres keeps the renamed table's existing
 * indexes/constraints and the responders.current_assignment_id FK valid.
 *
 * The per-line status enum changed (`expired` → `timed_out`, added `stood_down`),
 * so the old status CHECK is replaced and any legacy `expired` rows migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('responder_assignments', 'assignment_responders');

        Schema::table('assignment_responders', function (Blueprint $table): void {
            $table->foreignUuid('assignment_id')->nullable()->after('id')->constrained('assignments')->nullOnDelete();
            $table->string('role')->default(ResponderRole::Primary->value)->after('emergency_id');
            $table->unsignedInteger('attempt')->default(1)->after('assigned_by');
            $table->string('decline_reason')->nullable()->after('outcome');
            // The original offer timestamp was `assigned_at`; the aggregate model
            // calls it `offered_at`.
            $table->renameColumn('assigned_at', 'offered_at');
            $table->index('assignment_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // The aggregate's denormalised primary pointer can now reference this table.
        Schema::table('assignments', function (Blueprint $table): void {
            $table->foreign('primary_assignment_responder_id')
                ->references('id')->on('assignment_responders')->nullOnDelete();
        });

        // Migrate legacy status value, then swap the CHECK to the new enum set.
        DB::statement("UPDATE assignment_responders SET status = 'timed_out' WHERE status = 'expired'");
        DB::statement('ALTER TABLE assignment_responders DROP CONSTRAINT IF EXISTS responder_assignments_status_check');

        $statuses = collect(AssignmentResponderStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("ALTER TABLE assignment_responders ADD CONSTRAINT assignment_responders_status_check CHECK (status IN ({$statuses}))");

        $roles = collect(ResponderRole::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("ALTER TABLE assignment_responders ADD CONSTRAINT assignment_responders_role_check CHECK (role IN ({$roles}))");

        // One active primary line per assignment (complements the carried-over
        // "one active line per responder" partial unique).
        $active = collect(AssignmentResponderStatus::activeValues())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');
        DB::statement("CREATE UNIQUE INDEX assignment_responders_one_active_primary ON assignment_responders (assignment_id) WHERE role = 'primary' AND status IN ({$active})");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS assignment_responders_one_active_primary');
            DB::statement('ALTER TABLE assignment_responders DROP CONSTRAINT IF EXISTS assignment_responders_role_check');
            DB::statement('ALTER TABLE assignment_responders DROP CONSTRAINT IF EXISTS assignment_responders_status_check');

            Schema::table('assignments', function (Blueprint $table): void {
                $table->dropForeign(['primary_assignment_responder_id']);
            });

            // Restore the original status CHECK (best-effort).
            DB::statement("ALTER TABLE assignment_responders ADD CONSTRAINT responder_assignments_status_check CHECK (status IN ('offered','accepted','en_route','on_scene','completed','declined','expired','cancelled'))");
        }

        Schema::table('assignment_responders', function (Blueprint $table): void {
            $table->renameColumn('offered_at', 'assigned_at');
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropColumn(['role', 'attempt', 'decline_reason']);
        });

        Schema::rename('assignment_responders', 'responder_assignments');
    }
};
