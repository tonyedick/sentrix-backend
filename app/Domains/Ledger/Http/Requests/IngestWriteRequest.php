<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ingest a write reported by a source. Authentication is performed upstream by
 * the ledger.key middleware (X-Ledger-Key), which binds the resolved source onto
 * the request — so this request authorizes unconditionally.
 */
final class IngestWriteRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:40'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:200'],
            'ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organization_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
