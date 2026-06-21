<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddCardRequest extends FormRequest
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
            'brand' => ['sometimes', 'nullable', 'string', 'max:32'],
            // Store the last 4 digits ONLY — never accept a full PAN.
            'last4' => ['required', 'string', 'digits:4'],
        ];
    }
}
