<?php

declare(strict_types=1);

namespace App\Domains\Responder\Models;

use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organization-scoped operational profile for a responding user. Aggregate root
 * of the Responder domain; owns status/availability and (in later slices)
 * skills, certifications, location, duty shifts, and assignment history.
 */
final class Responder extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'on_duty',
        'current_assignment_id',
        'last_lat',
        'last_lng',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResponderStatus::class,
            'on_duty' => 'boolean',
            'last_lat' => 'decimal:7',
            'last_lng' => 'decimal:7',
            'last_seen_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'responder_skill')
            ->withPivot('proficiency')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ResponderCertification, $this>
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(ResponderCertification::class);
    }

    /**
     * @return HasMany<ResponderLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(ResponderLocation::class);
    }

    /**
     * @return HasMany<DutyShift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(DutyShift::class);
    }

    /**
     * Assignment line items for this responder (owned by the Assignment domain).
     *
     * @return HasMany<AssignmentResponder, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssignmentResponder::class);
    }

    /**
     * @return BelongsTo<AssignmentResponder, $this>
     */
    public function currentAssignment(): BelongsTo
    {
        return $this->belongsTo(AssignmentResponder::class, 'current_assignment_id');
    }

    /**
     * Responders a dispatcher can assign right now (backed by a partial index).
     *
     * @param  Builder<Responder>  $query
     * @return Builder<Responder>
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('status', ResponderStatus::Available->value)->where('on_duty', true);
    }
}
