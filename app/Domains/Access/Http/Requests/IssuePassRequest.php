<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Support\Enums\PassType;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssuePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::PassesIssue->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visitor_name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(PassType::values())],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after:valid_from'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
