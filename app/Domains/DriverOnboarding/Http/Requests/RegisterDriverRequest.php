<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver-scoped: any authenticated user may register as a driver.
 */
final class RegisterDriverRequest extends FormRequest
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
            'vehicle_make' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vehicle_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vehicle_plate' => ['sometimes', 'nullable', 'string', 'max:32'],
            'vehicle_color' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
