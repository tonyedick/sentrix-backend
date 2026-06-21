<?php

declare(strict_types=1);

namespace App\Domains\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttachQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // PLATFORM-scoped domain: gate on the platform SuperAdmin, not a per-org ability.
        // TODO: replace SuperAdmin gate with platform-staff roles (sales/onboarding_manager) + separation of duties (sales cannot convert) once a platform-staff RBAC layer exists.
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * The posted body IS the quote snapshot (an arbitrary pricing object stored
     * verbatim in the lead's `quote` jsonb column). Require it to be a non-empty
     * object.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'array'],
        ];
    }
}
