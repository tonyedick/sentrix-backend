<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Insurance\Support\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecideClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::InsuranceClaimsAdjust->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The terminal decision states a claim may be moved into.
            'decision' => ['required', Rule::in([ClaimStatus::Approved->value, ClaimStatus::Rejected->value])],
        ];
    }
}
