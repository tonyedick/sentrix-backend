<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Requests;

use App\Domains\Coordination\Support\Enums\DutyAction;
use App\Domains\Coordination\Support\Enums\DutyScopeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordDutyRequest extends FormRequest
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
            'action' => ['required', Rule::in(DutyAction::values())],
            'scope_type' => ['required', Rule::in(DutyScopeType::values())],
            'scope_id' => ['required', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
