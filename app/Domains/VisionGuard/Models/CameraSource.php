<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\VisionGuard\Support\Enums\SourceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A connected camera source for a user (phone, glasses, dashcam, CCTV, uploads).
 */
final class CameraSource extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'metadata' => 'array',
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
