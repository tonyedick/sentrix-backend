<?php

declare(strict_types=1);

namespace App\Domains\Responder\Models;

use App\Domains\Responder\Support\Enums\CertificationStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ResponderCertification extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'responder_id',
        'organization_id',
        'name',
        'authority',
        'issued_at',
        'expires_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => CertificationStatus::class,
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
