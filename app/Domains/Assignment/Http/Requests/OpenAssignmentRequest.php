<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::AssignmentsCreate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'uuid'],
            'dispatch_mode' => ['sometimes', Rule::in(['manual', 'auto'])],
            'required_supporting' => ['sometimes', 'integer', 'min:0', 'max:50'],
        ];
    }
}
