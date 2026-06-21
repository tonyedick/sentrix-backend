<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Requests;

use App\Domains\Ingest\Support\Enums\DetectionSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a SafeSignal report posted to /api/v1/signal/ingest.
 *
 * The tenant may be referenced as a UUID (organization_id) OR an org slug — both
 * arrive as a string; the controller resolves it with a Str::isUuid guard. So we
 * only require *a* tenant reference, not a uuid shape.
 *
 * Authentication is the service-token middleware (`core.service`), not a user.
 */
final class IngestSignalRequest extends FormRequest
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
            'organization_id' => ['required_without:org', 'string', 'max:255'],
            'org' => ['required_without:organization_id', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', 'max:128'],
            'severity' => ['sometimes', 'nullable', 'string', Rule::in(DetectionSeverity::values())],
            'summary' => ['required', 'string', 'max:2000'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subjects' => ['sometimes', 'array'],
            'subjects.*' => ['string', 'max:255'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
