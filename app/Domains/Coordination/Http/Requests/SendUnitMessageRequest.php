<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Requests;

use App\Domains\Coordination\Support\Enums\MessageDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendUnitMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
            'direction' => ['sometimes', Rule::in(MessageDirection::values())],
            'command_incident_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
