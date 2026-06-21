<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Support\Enums\GateDirection;
use App\Domains\Access\Support\Enums\GateResult;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LogGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::GateLog->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gate' => ['sometimes', 'string', 'max:60'],
            'direction' => ['sometimes', Rule::in(GateDirection::values())],
            'result' => ['sometimes', Rule::in(GateResult::values())],
            'visitor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
