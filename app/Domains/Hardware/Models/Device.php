<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Models;

use App\Domains\Hardware\Support\Enums\DeviceKind;
use App\Domains\Hardware\Support\Enums\DeviceStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical security hardware device in an organization's registry.
 *
 * Distinct from the push-token "devices" used for mobile notifications; this
 * maps to the hardware_devices table.
 */
final class Device extends Model
{
    use HasUuid;

    protected $table = 'hardware_devices';

    protected $fillable = [
        'organization_id',
        'registered_by',
        'kind',
        'serial',
        'name',
        'site',
        'zone',
        'status',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'status' => DeviceStatus::class,
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
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
