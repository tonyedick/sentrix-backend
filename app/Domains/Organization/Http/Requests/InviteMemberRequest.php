<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::MembersInvite->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => [
                'required',
                'string',
                // The role must already exist within this organization (team).
                Rule::exists('roles', 'name')->where('organization_id', $organizationId),
            ],
        ];
    }
}
