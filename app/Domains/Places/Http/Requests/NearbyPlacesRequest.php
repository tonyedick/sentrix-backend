<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Requests;

use App\Domains\Places\Support\Enums\PlaceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class NearbyPlacesRequest extends FormRequest
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
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:50', 'max:50000'],
            'category' => ['sometimes', Rule::in(PlaceCategory::values())],
            'open_now' => ['sometimes', 'boolean'],
        ];
    }
}
