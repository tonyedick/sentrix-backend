<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateOfferRequest extends FormRequest
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
            'origin_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'proposed_fare_cents' => ['required', 'integer', 'min:1'],
        ];
    }
}
