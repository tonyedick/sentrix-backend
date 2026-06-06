<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Authorization\Support\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RolesManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Reserved: creating a role named after a default/system role
                // would (via findOrCreate) hijack the existing one and re-sync
                // its permissions — a privilege-escalation vector.
                Rule::notIn([...OrganizationRole::values(), ...SystemRole::values()]),
            ],
            'permissions' => ['sometimes', 'array'],
            // Permissions are anchored to the `web` guard (all RBAC is).
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }
}
