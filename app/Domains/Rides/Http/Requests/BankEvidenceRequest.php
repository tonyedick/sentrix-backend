<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Requests;

use App\Domains\Rides\Support\Enums\EvidenceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BankEvidenceRequest extends FormRequest
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
            'kind' => ['required', Rule::in(EvidenceKind::values())],
            'url' => ['required', 'string', 'max:2048'],
        ];
    }
}
