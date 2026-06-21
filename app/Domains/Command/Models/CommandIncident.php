<?php

declare(strict_types=1);

namespace App\Domains\Command\Models;

use App\Domains\Command\Support\Enums\CommandIncidentSource;
use App\Domains\Command\Support\Enums\CommandIncidentStatus;
use App\Domains\Command\Support\Enums\IncidentCategory;
use App\Domains\Command\Support\Enums\IncidentSeverity;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The responder-side tracking envelope for an emergency routed to a lead
 * command. This is a PARALLEL record — it never mutates the org Incident
 * domain's own alert lifecycle.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class CommandIncident extends Model
{
    use HasUuid;

    protected $fillable = [
        'command_id',
        'agency_id',
        'category',
        'severity',
        'status',
        'source_type',
        'source_ref',
        'summary',
        'lat',
        'lng',
        'sla_dispatch_due_at',
        'sla_onscene_due_at',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'severity' => IncidentSeverity::class,
            'status' => CommandIncidentStatus::class,
            'source_type' => CommandIncidentSource::class,
            'lat' => 'float',
            'lng' => 'float',
            'sla_dispatch_due_at' => 'datetime',
            'sla_onscene_due_at' => 'datetime',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
