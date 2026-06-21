<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Support\Enums\GateDirection;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScanGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::GateScan->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12'],
            'gate' => ['sometimes', 'string', 'max:60'],
            'direction' => ['sometimes', Rule::in(GateDirection::values())],
        ];
    }
}
