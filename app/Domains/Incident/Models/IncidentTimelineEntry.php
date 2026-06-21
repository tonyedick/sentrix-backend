<?php

declare(strict_types=1);

namespace App\Domains\Incident\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only, incident-scoped timeline entry: the durable projection of an
 * incident's operational story (status changes, dispatch events, notifications,
 * AI annotations). Immutable by design — there is no `updated_at` and no soft
 * delete; entries are written once and never edited.
 */
final class IncidentTimelineEntry extends Model
{
    use HasUuid;

    /** Append-only: only the insert timestamp is managed. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'incident_id',
        'type',
        'source',
        'actor_id',
        'subject_type',
        'subject_id',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
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
     * The user who performed the action, if any (null = system-generated).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The related record across any domain (assignment, responder line, …).
     * Decoupled polymorphic reference — no cross-domain foreign key.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries for an organization (tenant scoping).
     *
     * @param  Builder<IncidentTimelineEntry>  $query
     * @return Builder<IncidentTimelineEntry>
     */
    public function scopeForOrganization(Builder $query, Organization|string $organization): Builder
    {
        return $query->where('organization_id', $organization instanceof Organization ? $organization->getKey() : $organization);
    }

    /**
     * Entries belonging to a single incident (the hot path, with chronological()).
     *
     * @param  Builder<IncidentTimelineEntry>  $query
     * @return Builder<IncidentTimelineEntry>
     */
    public function scopeForIncident(Builder $query, Incident|string $incident): Builder
    {
        return $query->where('incident_id', $incident instanceof Incident ? $incident->getKey() : $incident);
    }

    /**
     * Filter by producing domain (incident|assignment|notification|ai|system).
     *
     * @param  Builder<IncidentTimelineEntry>  $query
     * @return Builder<IncidentTimelineEntry>
     */
    public function scopeFromSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Order by business time (matches the (incident_id, occurred_at) index).
     *
     * @param  Builder<IncidentTimelineEntry>  $query
     * @return Builder<IncidentTimelineEntry>
     */
    public function scopeChronological(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('occurred_at', $direction);
    }

    public function isFromAi(): bool
    {
        return $this->source === 'ai';
    }

    public function isSystemGenerated(): bool
    {
        return $this->actor_id === null;
    }
}
