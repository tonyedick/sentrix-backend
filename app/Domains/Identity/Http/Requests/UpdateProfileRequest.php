<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'preferences' => ['sometimes', 'array'],
            'preferences.home_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferences.travel_mode' => ['sometimes', 'nullable', Rule::in(['driving', 'walking', 'public_transport', 'cycling'])],
            'preferences.add_vehicle_later' => ['sometimes', 'boolean'],
        ];
    }
}
