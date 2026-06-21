<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of a single write reported by a Ledger source.
 *
 * Immutable by design: there is no updated_at — entries are written once on
 * ingest and never edited.
 */
final class LedgerWrite extends Model
{
    use HasUuid;

    /** Append-only: only the insert timestamp is managed. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'ledger_source_id',
        'type',
        'summary',
        'ref',
        'organization_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LedgerSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LedgerSource::class, 'ledger_source_id');
    }
}
