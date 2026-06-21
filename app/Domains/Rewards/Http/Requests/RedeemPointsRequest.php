<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemPointsRequest extends FormRequest
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
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
