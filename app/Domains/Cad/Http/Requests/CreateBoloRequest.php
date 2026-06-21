<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Requests;

use App\Domains\Cad\Support\Enums\BoloKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issue a BOLO broadcast. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class CreateBoloRequest extends FormRequest
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
            'command_id' => ['required', 'uuid', Rule::exists('commands', 'id')],
            'kind' => ['sometimes', Rule::in(BoloKind::values())],
            'subject' => ['required', 'string', 'max:200'],
            'details' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
