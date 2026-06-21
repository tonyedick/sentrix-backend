<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a vision-provider payload posted to /api/v1/ingest/vision.
 *
 * Authentication is the service-token middleware (`core.service`), not a user.
 */
final class IngestVisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'uuid'],
            'provider' => ['required', 'string', 'max:64'],
            'camera_source_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'payload' => ['required', 'array'],
            'payload.detections' => ['sometimes', 'array'],
            'payload.detections.*.label' => ['sometimes', 'nullable', 'string', 'max:128'],
            'payload.detections.*.confidence' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'payload.behavior' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }
}
