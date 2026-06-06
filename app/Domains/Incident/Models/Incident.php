<?php

declare(strict_types=1);

namespace App\Domains\Incident\Models;

use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Incident extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'emergency_id',
        'opened_by',
        'assigned_to',
        'status',
        'severity',
        'title',
        'summary',
        'opened_at',
        'escalated_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'severity' => IncidentSeverity::class,
            'opened_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<Emergency, $this>
     */
    public function emergency(): BelongsTo
    {
        return $this->belongsTo(Emergency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
