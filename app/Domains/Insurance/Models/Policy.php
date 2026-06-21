<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Models;

use App\Domains\Insurance\Support\Enums\PolicyStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A risk-priced coverage agreement for an organization. Maps to the
 * insurance_policies table.
 */
final class Policy extends Model
{
    use HasUuid;

    protected $table = 'insurance_policies';

    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'status',
        'premium_cents',
        'currency',
        'coverage',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'premium_cents' => 'integer',
            'coverage' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
