<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Models;

use App\Domains\Ingest\Support\Enums\DetectionSeverity;
use App\Domains\Ingest\Support\Enums\DetectionSource;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single signal assessed by the Ingest pipeline (a camera/vision detection or
 * a SafeSignal report), together with the decision the engine made for it.
 */
final class DetectionEvent extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'source',
        'product',
        'camera_source_id',
        'type',
        'severity',
        'risk_score',
        'triggered',
        'incident_id',
        'site',
        'zone',
        'lat',
        'lng',
        'summary',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => DetectionSource::class,
            'severity' => DetectionSeverity::class,
            'risk_score' => 'integer',
            'triggered' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
