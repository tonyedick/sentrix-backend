<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Requests;

use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendQuoteRequest extends FormRequest
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
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
            'parcel_size' => ['required', Rule::in(ParcelSize::values())],
        ];
    }
}
