<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Requests;

use App\Domains\Command\Support\Enums\CommandIncidentSource;
use App\Domains\Command\Support\Enums\IncidentCategory;
use App\Domains\Command\Support\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Route an incident to its lead command. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class RouteIncidentRequest extends FormRequest
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
            'category' => ['sometimes', 'nullable', Rule::in(IncidentCategory::values())],
            'severity' => ['required', Rule::in(IncidentSeverity::values())],
            'summary' => ['required', 'string', 'max:1000'],
            'country' => ['sometimes', 'string', 'size:2'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'source_type' => ['sometimes', Rule::in(CommandIncidentSource::values())],
            'source_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
