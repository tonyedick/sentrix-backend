<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Act on a command incident. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class ActOnIncidentRequest extends FormRequest
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
            'action' => [
                'required',
                Rule::in(['acknowledge', 'en_route', 'on_scene', 'escalate', 'resolve', 'stand_down']),
            ],
        ];
    }
}
