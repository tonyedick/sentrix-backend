<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Requests;

use App\Domains\Wallet\Support\Enums\TopupMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitiateTopupRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::in(TopupMethod::values())],
        ];
    }
}
