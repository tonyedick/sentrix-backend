<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Platform-staff (SuperAdmin) action for now.
 * TODO: accept command roles (dispatch_coordinator) once a platform-staff RBAC layer exists.
 */
final class RequestMutualAidRequest extends FormRequest
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
            'command_incident_id' => ['required', 'uuid'],
            'requesting_command_id' => ['required', 'uuid'],
            'responding_command_id' => ['required', 'uuid', 'different:requesting_command_id'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
