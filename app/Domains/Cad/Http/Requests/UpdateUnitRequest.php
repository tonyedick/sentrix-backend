<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Requests;

use App\Domains\Cad\Support\Enums\UnitKind;
use App\Domains\Cad\Support\Enums\UnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a field unit (AVL / status / capabilities / area). All
 * fields optional. PLATFORM-scoped: gated on SuperAdmin.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class UpdateUnitRequest extends FormRequest
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
            'call_sign' => ['sometimes', 'string', 'max:40'],
            'kind' => ['sometimes', Rule::in(UnitKind::values())],
            'capabilities' => ['sometimes', 'array', 'max:12'],
            'capabilities.*' => ['string', 'max:30'],
            'crew' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', Rule::in(UnitStatus::values())],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
