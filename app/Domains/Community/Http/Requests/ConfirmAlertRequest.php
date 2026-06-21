<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Requests;

use App\Domains\Community\Support\Enums\AlertImpact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'still_active' => ['sometimes', 'boolean'],
            'impact' => ['sometimes', Rule::in(AlertImpact::values())],
            'comment' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
