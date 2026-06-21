<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual dispatch override. SuperAdmin-gated (platform staff).
 *
 * TODO: replace with a rides:ops / rides:dispatch platform-staff role.
 */
final class ReassignRequest extends FormRequest
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
            'driver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
