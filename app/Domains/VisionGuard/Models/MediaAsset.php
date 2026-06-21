<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A captured media asset (clip/photo). Bytes live in object storage; this row
 * holds metadata + the storage key, linkable to a trip or emergency.
 */
final class MediaAsset extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'camera_source_id',
        'storage_key',
        'content_type',
        'size_bytes',
        'status',
        'trip_id',
        'emergency_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
