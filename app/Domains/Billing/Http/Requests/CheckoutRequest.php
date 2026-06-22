<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
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
            'plan_key' => ['required', Rule::in(array_keys((array) config('sentrix.billing.plans', [])))],
            'region' => ['sometimes', 'nullable', Rule::in(array_keys((array) config('sentrix.billing.regions', [])))],
        ];
    }
}
