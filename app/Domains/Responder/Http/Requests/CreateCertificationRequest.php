<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Responder\Support\Enums\CertificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RespondersManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'authority' => ['sometimes', 'nullable', 'string', 'max:255'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(CertificationStatus::values())],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
