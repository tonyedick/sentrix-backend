<?php

declare(strict_types=1);

namespace App\Domains\Cad\Models;

use App\Domains\Cad\Support\Enums\BoloKind;
use App\Domains\Cad\Support\Enums\BoloStatus;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A BOLO / all-points / officer-safety broadcast issued by a command, scoped to
 * its agency.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class Bolo extends Model
{
    use HasUuid;

    protected $fillable = [
        'agency_id',
        'command_id',
        'kind',
        'subject',
        'details',
        'status',
        'issued_by',
        'issued_at',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BoloKind::class,
            'status' => BoloStatus::class,
            'details' => 'array',
            'issued_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
