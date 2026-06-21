<?php

declare(strict_types=1);

use App\Domains\Responder\Support\Enums\ResponderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Responder is an organization-scoped operational profile for a user who can
 * respond to incidents and emergencies. One profile per (organization, user).
 *
 * The `on_duty` flag mirrors the on-duty status set and is the column the
 * dispatch query filters on together with `status`; a partial index covers the
 * hot "who can I dispatch right now" lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responders', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status')->default(ResponderStatus::OffDuty->value);
            $table->boolean('on_duty')->default(false);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // A user has at most one responder profile per organization.
            $table->unique(['organization_id', 'user_id']);
            // Roster boards filter by organization + status.
            $table->index(['organization_id', 'status']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = collect(ResponderStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');

        DB::statement("ALTER TABLE responders ADD CONSTRAINT responders_status_check CHECK (status IN ({$allowed}))");

        // Hot path: the set of responders a dispatcher can assign right now.
        DB::statement("CREATE INDEX responders_assignable_per_org_index ON responders (organization_id) WHERE status = 'available' AND on_duty = true");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS responders_assignable_per_org_index');
            DB::statement('ALTER TABLE responders DROP CONSTRAINT IF EXISTS responders_status_check');
        }

        Schema::dropIfExists('responders');
    }
};
