<?php

declare(strict_types=1);

namespace App\Domains\Retention\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Retention\Support\Enums\ExportFormat;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable archive export manifest: the durable record of one "archive-first"
 * bundle of Evidence observations. Append-only (no updated_at).
 */
final class RetentionExport extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'exported_by',
        'format',
        'count',
        'manifest',
    ];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'count' => 'integer',
            'manifest' => 'array',
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
    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
