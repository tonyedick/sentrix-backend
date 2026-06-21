<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Requests;

use App\Domains\Command\Support\Enums\CommandTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a command node. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class CreateCommandRequest extends FormRequest
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
            'agency_id' => ['required', 'uuid', Rule::exists('agencies', 'id')],
            'parent_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('commands', 'id')],
            'tier' => ['required', Rule::in(CommandTier::values())],
            'name' => ['required', 'string', 'max:255'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
