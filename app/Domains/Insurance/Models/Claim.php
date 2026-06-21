<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Models;

use App\Domains\Insurance\Support\Enums\ClaimStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A claim filed against an insurance policy, with an approve/reject decision
 * trail. Maps to the insurance_claims table.
 */
final class Claim extends Model
{
    use HasUuid;

    protected $table = 'insurance_claims';

    protected $fillable = [
        'organization_id',
        'policy_id',
        'filed_by',
        'amount_cents',
        'currency',
        'status',
        'description',
        'decided_by',
        'decided_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'amount_cents' => 'integer',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
