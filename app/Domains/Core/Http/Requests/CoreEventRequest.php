<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an inbound product/detection event posted to /api/v1/core/events.
 *
 * Authentication is the service-token middleware (`core.service`), not a user —
 * so authorize() is unconditional here; the trust boundary lives in
 * {@see \App\Domains\Core\Http\Middleware\AuthenticateCoreService}.
 */
final class CoreEventRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'string', 'max:32'],
            'summary' => ['required', 'string', 'max:2000'],
            'org' => ['required', 'string', 'max:255'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subjects' => ['sometimes', 'array'],
            'subjects.*' => ['string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'array'],
            'location.lat' => ['sometimes', 'nullable', 'numeric'],
            'location.lng' => ['sometimes', 'nullable', 'numeric'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
