<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteRideRequest extends FormRequest
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
            'rating' => ['sometimes', 'nullable', 'integer', 'between:1,5'],
            'tip_cents' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
