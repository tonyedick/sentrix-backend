<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptBidRequest extends FormRequest
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
            'bid_id' => ['required', 'string', 'uuid'],
        ];
    }
}
