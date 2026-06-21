<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dispatch (assign) a unit to a command incident. PLATFORM-scoped: gated on
 * SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class DispatchUnitRequest extends FormRequest
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
            'unit_id' => ['required', 'uuid', Rule::exists('units', 'id')],
        ];
    }
}
