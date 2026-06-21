<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff-scoped: record an in-person inspection outcome (and, on pass, install
 * the Sentrix hardware kit + activate). PLATFORM-scoped — gated on SuperAdmin.
 *
 * TODO: replace with platform-staff 'staff:drivers' role.
 */
final class RecordInspectionRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['pass', 'fail'])],
            'checklist' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
