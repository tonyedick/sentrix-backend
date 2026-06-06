<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Requests;

use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ownership transfer is reserved to the current owner (or a platform SuperAdmin)
 * — it is not delegable via a permission.
 */
final class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $user = $this->user();

        if ($user === null || ! $organization instanceof Organization) {
            return false;
        }

        return $user->getKey() === $organization->owner_id || $user->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return [
            'user_id' => [
                'required',
                'uuid',
                // The recipient must already be a member of this organization.
                Rule::exists('organization_user', 'user_id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
