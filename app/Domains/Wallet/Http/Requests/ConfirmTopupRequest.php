<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmTopupRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:64'],
        ];
    }
}
