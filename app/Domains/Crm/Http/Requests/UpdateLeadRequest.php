<?php

declare(strict_types=1);

namespace App\Domains\Crm\Http\Requests;

use App\Domains\Crm\Support\Enums\ClientType;
use App\Domains\Crm\Support\Enums\LeadStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // PLATFORM-scoped domain: gate on the platform SuperAdmin, not a per-org ability.
        // TODO: replace SuperAdmin gate with platform-staff roles (sales/onboarding_manager) + separation of duties (sales cannot convert) once a platform-staff RBAC layer exists.
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['sometimes', Rule::in(LeadStage::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'client_type' => ['sometimes', Rule::in(ClientType::values())],
        ];
    }
}
