<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GeocodeRequest extends FormRequest
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
            'address' => ['required', 'string', 'max:512'],
        ];
    }
}
