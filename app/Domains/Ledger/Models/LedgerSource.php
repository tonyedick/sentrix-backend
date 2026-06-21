<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Models;

use App\Domains\Ledger\Support\Enums\SourceKind;
use App\Domains\Ledger\Support\Enums\SourceStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered data source in the Sentrix Ledger — a product, service, device,
 * or integration that reports writes into the ecosystem write-spine.
 *
 * PLATFORM-scoped: organization_id is a nullable cross-tenant tag, not an FK.
 * The raw ingest key is never persisted; only its hash (key_hash) is stored and
 * the raw value is surfaced once at creation/rotation.
 */
final class LedgerSource extends Model
{
    use HasUuid;

    protected $fillable = [
        'slug',
        'name',
        'product',
        'kind',
        'organization_id',
        'status',
        'key_hash',
        'last_write_at',
        'write_count',
        'stale_alerted',
        'metadata',
    ];

    /**
     * key_hash is hidden so it is never accidentally serialized; resources expose
     * only a masked fingerprint.
     *
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
            'status' => SourceStatus::class,
            'last_write_at' => 'datetime',
            'write_count' => 'integer',
            'stale_alerted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<LedgerWrite, $this>
     */
    public function writes(): HasMany
    {
        return $this->hasMany(LedgerWrite::class);
    }
}
