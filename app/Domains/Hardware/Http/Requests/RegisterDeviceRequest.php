<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Hardware\Support\Enums\DeviceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::HardwareRegister->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(DeviceKind::values())],
            'serial' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
