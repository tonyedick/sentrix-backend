<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

use App\Domains\Access\Support\Enums\GateDirection;
use App\Domains\Access\Support\Enums\GateResult;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable gate log entry — appended on every scan (granted or denied) and
 * for manual entries. Append-only: there is no `updated_at`.
 */
final class GateEvent extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $table = 'gate_events';

    protected $fillable = [
        'organization_id',
        'pass_id',
        'officer_id',
        'gate',
        'direction',
        'result',
        'reason',
        'visitor_name',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => GateDirection::class,
            'result' => GateResult::class,
            'recorded_at' => 'datetime',
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
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
