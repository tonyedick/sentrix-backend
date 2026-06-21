<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Requests;

use App\Domains\VisionGuard\Support\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterSourceRequest extends FormRequest
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
            'type' => ['required', Rule::in(SourceType::values())],
            'label' => ['required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
