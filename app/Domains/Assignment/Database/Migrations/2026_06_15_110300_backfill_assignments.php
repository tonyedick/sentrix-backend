<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills the Assignment aggregate for any pre-existing line rows: each
 * incident-linked line gets (or joins) an Assignment for its incident, as the
 * primary responder. Emergency-only legacy lines are left unparented (the
 * domain is incident-scoped going forward). A no-op on a fresh database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        DB::table('assignment_responders')
            ->whereNull('assignment_id')
            ->whereNotNull('incident_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $line) use ($now): void {
                $assignmentId = DB::table('assignments')
                    ->where('incident_id', $line->incident_id)
                    ->value('id');

                if ($assignmentId === null) {
                    $assignmentId = (string) Str::orderedUuid();
                    DB::table('assignments')->insert([
                        'id' => $assignmentId,
                        'organization_id' => $line->organization_id,
                        'incident_id' => $line->incident_id,
                        'status' => 'pending',
                        'dispatch_mode' => 'manual',
                        'required_primary' => true,
                        'required_supporting' => 0,
                        'escalation_level' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('assignment_responders')
                    ->where('id', $line->id)
                    ->update(['assignment_id' => $assignmentId, 'role' => 'primary']);

                if (in_array($line->status, ['accepted', 'en_route', 'on_scene'], true)) {
                    DB::table('assignments')->where('id', $assignmentId)->update([
                        'primary_assignment_responder_id' => $line->id,
                        'status' => 'filled',
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Non-reversible data backfill; the structural rollback lives in the
        // rename migration. Leaving assignments in place on rollback is safe.
    }
};
