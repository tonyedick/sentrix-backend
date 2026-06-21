<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckInRequest extends FormRequest
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
            'ok' => ['required', 'boolean'],
        ];
    }
}
