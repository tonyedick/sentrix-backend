<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Requests;

use App\Domains\Coordination\Support\Enums\TaskingKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RouteTaskingRequest extends FormRequest
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
            'kind' => ['sometimes', Rule::in(TaskingKind::values())],
            'title' => ['required', 'string', 'max:255'],
            'ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assignee' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
