<?php

declare(strict_types=1);

use App\Domains\Responder\Support\Enums\DutyShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A scheduled on/off-duty window for a responder. The duty sweep activates
 * shifts at their start (putting the responder on duty) and completes them at
 * their end. A partial index covers the sweep's hot lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_shifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('responder_id')->constrained('responders')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default(DutyShiftStatus::Scheduled->value);
            $table->string('source')->default('manual');

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'starts_at']);
            $table->index(['responder_id', 'status']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = collect(DutyShiftStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');

        DB::statement("ALTER TABLE duty_shifts ADD CONSTRAINT duty_shifts_status_check CHECK (status IN ({$allowed}))");

        // Sweep hot paths: shifts to start and shifts to close.
        DB::statement("CREATE INDEX duty_shifts_to_start_index ON duty_shifts (starts_at) WHERE status = 'scheduled'");
        DB::statement("CREATE INDEX duty_shifts_to_close_index ON duty_shifts (ends_at) WHERE status = 'active'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS duty_shifts_to_start_index');
            DB::statement('DROP INDEX IF EXISTS duty_shifts_to_close_index');
            DB::statement('ALTER TABLE duty_shifts DROP CONSTRAINT IF EXISTS duty_shifts_status_check');
        }

        Schema::dropIfExists('duty_shifts');
    }
};
