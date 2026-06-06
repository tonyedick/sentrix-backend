<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

final class IngestLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::TrackingIngest->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Bounded batch so a single request can't be unboundedly large.
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
