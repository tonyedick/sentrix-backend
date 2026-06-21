<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver-scoped: toggle availability online/offline.
 */
final class SetOnlineRequest extends FormRequest
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
            'online' => ['required', 'boolean'],
        ];
    }
}
