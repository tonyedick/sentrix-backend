<?php

declare(strict_types=1);

namespace App\Domains\Incident\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::IncidentsCreate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'severity' => ['sometimes', Rule::in(IncidentSeverity::values())],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // Cross-references validated against the organization in the controller.
            'emergency_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
