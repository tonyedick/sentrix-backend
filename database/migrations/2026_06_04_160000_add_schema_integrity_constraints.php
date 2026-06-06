<?php

declare(strict_types=1);

use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Emergency\Support\Enums\EmergencyStatus;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Trip\Support\Enums\TripStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level integrity hardening:
 *
 *  - explicit indexes on every foreign key (PostgreSQL does NOT auto-index FK
 *    columns — only PK/unique — so cascade deletes and lookups would seq-scan);
 *  - CHECK constraints pinning enum columns to their valid values, so a bad
 *    status/severity can't exist even via raw SQL or a future code regression;
 *  - partial indexes for the hot "live records per organization" queries.
 *
 * CHECK + partial indexes are PostgreSQL-specific and guarded accordingly; the
 * plain FK indexes are portable.
 */
return new class extends Migration
{
    /**
     * Foreign keys lacking a covering index (the leading column of a composite
     * index already covers the rest).
     *
     * @var array<string, list<string>>
     */
    private array $foreignKeyIndexes = [
        'organizations' => ['owner_id'],
        'organization_user' => ['user_id'],
        'organization_invitations' => ['invited_by'],
        'emergencies' => ['user_id', 'acknowledged_by', 'resolved_by'],
        'incidents' => ['opened_by', 'assigned_to'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeyIndexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column, "{$table}_{$column}_index");
                }
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->addEnumCheck('trips', 'status', TripStatus::values());
        $this->addEnumCheck('emergencies', 'status', EmergencyStatus::values());
        $this->addEnumCheck('emergencies', 'severity', EmergencySeverity::values());
        $this->addEnumCheck('incidents', 'status', IncidentStatus::values());
        $this->addEnumCheck('incidents', 'severity', IncidentSeverity::values());

        // Partial indexes for the hot "what is live for this org" boards and the
        // overdue sweep — they index only the small live subset, not historical rows.
        DB::statement("CREATE INDEX trips_live_per_org_index ON trips (organization_id) WHERE status IN ('active', 'overdue')");
        DB::statement("CREATE INDEX trips_overdue_sweep_index ON trips (expected_arrival_at) WHERE status = 'active'");
        DB::statement("CREATE INDEX emergencies_live_per_org_index ON emergencies (organization_id) WHERE status IN ('triggered', 'acknowledged')");
        DB::statement("CREATE INDEX incidents_open_per_org_index ON incidents (organization_id) WHERE status IN ('open', 'investigating', 'escalated')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['trips_status', 'emergencies_status', 'emergencies_severity', 'incidents_status', 'incidents_severity'] as $constraint) {
                [$table] = explode('_', $constraint, 2);
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}_check");
            }

            foreach (['trips_live_per_org_index', 'trips_overdue_sweep_index', 'emergencies_live_per_org_index', 'incidents_open_per_org_index'] as $index) {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            }
        }

        foreach ($this->foreignKeyIndexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex("{$table}_{$column}_index");
                }
            });
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function addEnumCheck(string $table, string $column, array $values): void
    {
        $allowed = collect($values)
            ->map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
    }
};
