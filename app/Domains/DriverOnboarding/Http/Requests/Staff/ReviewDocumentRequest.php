<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff-scoped: decide on a single driver document. PLATFORM-scoped — gated on
 * SuperAdmin, not an organization permission.
 *
 * TODO: replace with platform-staff 'staff:drivers' role.
 */
final class ReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'uuid'],
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
