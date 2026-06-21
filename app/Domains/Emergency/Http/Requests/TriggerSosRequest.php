<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Requests;

use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Consumer SOS trigger (user-scoped). No organization — the serving org is
 * resolved server-side (ADR-0001).
 */
final class TriggerSosRequest extends FormRequest
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
            'severity' => ['sometimes', Rule::in(EmergencySeverity::values())],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'trip_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
