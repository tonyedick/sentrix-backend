<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Models;

use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Emergency\Support\Enums\EmergencyStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Emergency extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'emergencies';

    protected $fillable = [
        'organization_id',
        'user_id',
        'trip_id',
        'status',
        'severity',
        'message',
        'lat',
        'lng',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmergencyStatus::class,
            'severity' => EmergencySeverity::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
