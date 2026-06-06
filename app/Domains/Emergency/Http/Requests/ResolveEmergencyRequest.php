<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveEmergencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::EmergenciesResolve->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
