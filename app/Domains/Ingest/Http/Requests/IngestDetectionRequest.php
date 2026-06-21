<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a native detection posted to /api/v1/ingest/detections.
 *
 * Authentication is the service-token middleware (`core.service`), not a user —
 * authorize() is unconditional; the middleware is the trust boundary.
 */
final class IngestDetectionRequest extends FormRequest
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
            'camera_source_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'product' => ['sometimes', 'nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'max:128'],
            'confidence' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
