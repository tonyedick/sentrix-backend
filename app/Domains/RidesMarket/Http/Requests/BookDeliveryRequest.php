<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Requests;

use App\Domains\RidesMarket\Support\Enums\DeliveryPaymentMethod;
use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BookDeliveryRequest extends FormRequest
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
            'pickup_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
            'parcel_size' => ['required', Rule::in(ParcelSize::values())],
            'payment_method' => ['required', Rule::in(DeliveryPaymentMethod::values())],
            'cod_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'recipient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
