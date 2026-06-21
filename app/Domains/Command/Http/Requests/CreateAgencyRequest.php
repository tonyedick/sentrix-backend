<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Requests;

use App\Domains\Command\Support\Enums\AgencyStatus;
use App\Domains\Command\Support\Enums\IncidentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Onboard a responder agency. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class CreateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12', Rule::unique('agencies', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'size:2'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::in(IncidentCategory::values())],
            'hotline' => ['sometimes', 'nullable', 'string', 'max:32'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'status' => ['sometimes', Rule::in(AgencyStatus::values())],
        ];
    }
}
