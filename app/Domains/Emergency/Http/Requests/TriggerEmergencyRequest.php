<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TriggerEmergencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::EmergenciesTrigger->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'severity' => ['sometimes', Rule::in(EmergencySeverity::values())],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            // Must reference a trip in the same organization (checked in the controller).
            'trip_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
