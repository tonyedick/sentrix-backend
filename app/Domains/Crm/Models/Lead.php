<?php

declare(strict_types=1);

namespace App\Domains\Crm\Models;

use App\Domains\Crm\Support\Enums\ClientType;
use App\Domains\Crm\Support\Enums\LeadStage;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CRM lead: a sales prospect in the new -> qualified -> quoted -> won|lost
 * pipeline. Platform-scoped (no organization_id) — a lead pre-dates the tenant
 * it eventually becomes. The tenant is linked via converted_organization_id on
 * convert.
 */
final class Lead extends Model
{
    use HasUuid;

    protected $fillable = [
        'converted_organization_id',
        'created_by',
        'name',
        'client_type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'region',
        'source',
        'stage',
        'quote',
        'notes',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
            'stage' => LeadStage::class,
            'quote' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function convertedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'converted_organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
