<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch location fixes for a consumer's own trip (mirrors the operational
 * tracking ingest contract).
 */
final class IngestTripLocationsRequest extends FormRequest
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
            'fixes' => ['required', 'array', 'min:1', 'max:200'],
            'fixes.*.id' => ['required', 'uuid'],
            'fixes.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'fixes.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'fixes.*.recorded_at' => ['required', 'date'],
            'fixes.*.accuracy' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fixes.*.speed' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fixes.*.heading' => ['sometimes', 'nullable', 'numeric', 'between:0,360'],
        ];
    }
}
