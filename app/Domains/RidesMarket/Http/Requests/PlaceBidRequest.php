<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Requests;

use App\Domains\RidesMarket\Support\Enums\BidKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlaceBidRequest extends FormRequest
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
            'kind' => ['required', Rule::in(BidKind::values())],
            'driver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
