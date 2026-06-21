<?php

declare(strict_types=1);

namespace App\Domains\Responder\Models;

use App\Domains\Responder\Support\Enums\DutyShiftStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DutyShift extends Model
{
    use HasUuid;

    protected $fillable = [
        'responder_id',
        'organization_id',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => DutyShiftStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Responder, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class);
    }
}
