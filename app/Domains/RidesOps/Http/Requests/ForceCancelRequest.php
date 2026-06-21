<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Force-cancel a ride. SuperAdmin-gated (platform staff).
 *
 * TODO: replace with a rides:ops / rides:dispatch platform-staff role.
 */
final class ForceCancelRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
