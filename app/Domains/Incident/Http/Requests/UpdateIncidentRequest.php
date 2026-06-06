<?php

declare(strict_types=1);

namespace App\Domains\Incident\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::IncidentsUpdate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', Rule::in(IncidentSeverity::values())],
            'assigned_to' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
