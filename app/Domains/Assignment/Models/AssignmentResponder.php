<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Models;

use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\Responder;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single responder's participation in an assignment. Carries the offer
 * lifecycle, role (primary/supporting), and milestone timestamps. Reassignment
 * stands a line down and creates a new one, so history is never destroyed.
 *
 * (Formerly the Responder domain's ResponderAssignment; now a line item under
 * the Assignment aggregate.)
 */
final class AssignmentResponder extends Model
{
    use HasUuid;

    protected $fillable = [
        'assignment_id',
        'organization_id',
        'responder_id',
        'incident_id',
        'emergency_id',
        'role',
        'status',
        'attempt',
        'assigned_by',
        'offered_at',
        'accepted_at',
        'en_route_at',
        'on_scene_at',
        'completed_at',
        'released_at',
        'decline_reason',
        'outcome',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'role' => ResponderRole::class,
            'status' => AssignmentResponderStatus::class,
            'attempt' => 'integer',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'en_route_at' => 'datetime',
            'on_scene_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Responder, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class);
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<Emergency, $this>
     */
    public function emergency(): BelongsTo
    {
        return $this->belongsTo(Emergency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
