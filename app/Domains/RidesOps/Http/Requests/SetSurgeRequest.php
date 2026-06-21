<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pin or release the manual surge. SuperAdmin-gated (platform staff).
 *
 * TODO: replace with a rides:ops / rides:dispatch platform-staff role.
 */
final class SetSurgeRequest extends FormRequest
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
            'release' => ['sometimes', 'boolean'],
            // Required to pin; ignored on release.
            'multiplier' => ['required_without:release', 'nullable', 'numeric', 'min:1.0', 'max:3.0'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
