<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Models;

use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The incident-scoped coordination record: the aggregate root of the Assignment
 * domain. Holds the required composition and overall status; its
 * AssignmentResponder lines carry each responder's participation.
 */
final class Assignment extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'incident_id',
        'status',
        'dispatch_mode',
        'required_primary',
        'required_supporting',
        'primary_assignment_responder_id',
        'opened_by',
        'acceptance_deadline_at',
        'escalation_level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'required_primary' => 'boolean',
            'required_supporting' => 'integer',
            'acceptance_deadline_at' => 'datetime',
            'escalation_level' => 'integer',
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

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return HasMany<AssignmentResponder, $this>
     */
    public function responders(): HasMany
    {
        return $this->hasMany(AssignmentResponder::class);
    }

    /**
     * @return BelongsTo<AssignmentResponder, $this>
     */
    public function primaryLine(): BelongsTo
    {
        return $this->belongsTo(AssignmentResponder::class, 'primary_assignment_responder_id');
    }

    /**
     * @return HasMany<AssignmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssignmentEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
