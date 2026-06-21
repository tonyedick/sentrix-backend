<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Evidence\Support\Enums\ObservationKind;
use App\Domains\Evidence\Support\Enums\ObservationSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Batch ingest of observations into the vault (max 100 per call).
 */
final class ObserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::EvidenceIngest->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observations' => ['required', 'array', 'min:1', 'max:100'],
            'observations.*.kind' => ['required', Rule::in(ObservationKind::values())],
            'observations.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'observations.*.attributes' => ['sometimes', 'nullable', 'array'],
            'observations.*.plate' => ['sometimes', 'nullable', 'string', 'max:32'],
            'observations.*.confidence' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'observations.*.severity' => ['sometimes', 'nullable', Rule::in(ObservationSeverity::values())],
            'observations.*.snapshot_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'observations.*.clip_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'observations.*.lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'observations.*.lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'observations.*.observed_at' => ['sometimes', 'nullable', 'date'],
            'observations.*.camera_source_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
