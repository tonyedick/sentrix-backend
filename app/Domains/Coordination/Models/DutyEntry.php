<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Models;

use App\Domains\Coordination\Support\Enums\DutyAction;
use App\Domains\Coordination\Support\Enums\DutyScopeType;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * An append-only duty-book entry (sign_in / sign_out / handover) per control
 * room. Mirrors the SentrixGoBackend command router duty book.
 *
 * Append-only: no updated_at (UPDATED_AT = null).
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class DutyEntry extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'scope_type',
        'scope_id',
        'person_name',
        'role',
        'action',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => DutyScopeType::class,
            'action' => DutyAction::class,
            'recorded_at' => 'datetime',
        ];
    }
}
