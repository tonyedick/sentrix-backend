<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Requests;

use App\Domains\Community\Support\Enums\AlertCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class NearbyAlertsRequest extends FormRequest
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
            'category' => ['sometimes', Rule::in(AlertCategory::values())],
        ];
    }
}
