<?php

declare(strict_types=1);

namespace App\Domains\Command\Models;

use App\Domains\Command\Support\Enums\AgencyStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A national responder agency (Police, Fire, FRSC, …). It declares the
 * emergency categories it leads, which is what derives routing per country.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class Agency extends Model
{
    use HasUuid;

    protected $fillable = [
        'code',
        'name',
        'country',
        'categories',
        'hotline',
        'color',
        'logo_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'status' => AgencyStatus::class,
        ];
    }

    /**
     * @return HasMany<Command, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }

    /**
     * @return HasMany<CommandIncident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(CommandIncident::class);
    }
}
