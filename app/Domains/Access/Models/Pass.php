<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

use App\Domains\Access\Support\Enums\PassStatus;
use App\Domains\Access\Support\Enums\PassType;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A time-bound visitor access credential. The `code` is the 6+ char value a
 * gate officer scans. Expiry is derived from the validity window at scan time.
 */
final class Pass extends Model
{
    use HasUuid;

    protected $table = 'access_passes';

    protected $fillable = [
        'organization_id',
        'host_id',
        'code',
        'visitor_name',
        'type',
        'status',
        'valid_from',
        'valid_until',
        'uses_count',
        'revoked_by',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PassType::class,
            'status' => PassStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'uses_count' => 'integer',
            'revoked_at' => 'datetime',
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
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return HasMany<GateEvent, $this>
     */
    public function gateEvents(): HasMany
    {
        return $this->hasMany(GateEvent::class);
    }

    /**
     * Whether the pass is usable right now: active and within its validity
     * window. Returns the reason it is NOT usable, or null when it is.
     */
    public function denialReason(?Carbon $at = null): ?string
    {
        $at ??= Carbon::now();

        if ($this->status === PassStatus::Revoked) {
            return 'revoked';
        }

        if ($this->status === PassStatus::Consumed) {
            return 'consumed';
        }

        if ($this->valid_from !== null && $at->lt($this->valid_from)) {
            return 'not_yet_valid';
        }

        if ($this->valid_until !== null && $at->gt($this->valid_until)) {
            return 'expired';
        }

        return null;
    }
}
