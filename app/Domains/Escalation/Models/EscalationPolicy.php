<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-organization escalation policy: configurable thresholds + toggles for the
 * three escalation types. Resolved by EscalationPolicyResolver, which returns a
 * transient config-default instance for organizations without a saved row.
 */
final class EscalationPolicy extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'incident_unassigned_seconds',
        'assignment_unaccepted_seconds',
        'responder_no_progression_seconds',
        'incident_escalation_enabled',
        'assignment_escalation_enabled',
        'responder_escalation_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'incident_unassigned_seconds' => 'integer',
            'assignment_unaccepted_seconds' => 'integer',
            'responder_no_progression_seconds' => 'integer',
            'incident_escalation_enabled' => 'boolean',
            'assignment_escalation_enabled' => 'boolean',
            'responder_escalation_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
