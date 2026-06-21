<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Requests;

use App\Domains\Ledger\Support\Enums\SourceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Onboard a Ledger source. PLATFORM-scoped: gated on SuperAdmin, not an
 * organization permission.
 */
final class OnboardSourceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(SourceKind::values())],
            'product' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:40', 'alpha_dash', Rule::unique('ledger_sources', 'slug')],
            'organization_id' => ['sometimes', 'nullable', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
