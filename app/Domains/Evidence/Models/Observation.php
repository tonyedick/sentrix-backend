<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Models;

use App\Domains\Evidence\Support\Enums\ObservationKind;
use App\Domains\Evidence\Support\Enums\ObservationSeverity;
use App\Domains\Evidence\Support\Enums\RetentionTier;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\VisionGuard\Models\CameraSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single forensic observation in the evidence vault.
 */
final class Observation extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'camera_source_id',
        'kind',
        'label',
        'attributes',
        'plate',
        'confidence',
        'severity',
        'snapshot_url',
        'clip_url',
        'lat',
        'lng',
        'observed_at',
        'hold',
        'bookmarked',
        'sealed',
        'retention_tier',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ObservationKind::class,
            'severity' => ObservationSeverity::class,
            'retention_tier' => RetentionTier::class,
            'attributes' => 'array',
            'confidence' => 'decimal:4',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'observed_at' => 'datetime',
            'hold' => 'boolean',
            'bookmarked' => 'boolean',
            'sealed' => 'boolean',
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
     * @return BelongsTo<CameraSource, $this>
     */
    public function cameraSource(): BelongsTo
    {
        return $this->belongsTo(CameraSource::class);
    }
}
