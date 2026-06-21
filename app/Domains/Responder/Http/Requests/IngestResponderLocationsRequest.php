<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization is decided in the controller (self vs. manage) because it
 * depends on whether the target responder profile is the caller's own.
 */
final class IngestResponderLocationsRequest extends FormRequest
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
            'fixes.*.client_fix_id' => ['required', 'string', 'max:128'],
            'fixes.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'fixes.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'fixes.*.recorded_at' => ['required', 'date'],
            'fixes.*.accuracy' => ['sometimes', 'nullable', 'numeric'],
            'fixes.*.speed' => ['sometimes', 'nullable', 'numeric'],
            'fixes.*.heading' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}
