<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Place or release a legal hold on an observation. The `hold` flag is optional —
 * omit it to flip the current state.
 */
final class HoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::EvidenceHold->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hold' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
