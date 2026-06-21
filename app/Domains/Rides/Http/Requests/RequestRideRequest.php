<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Requests;

use App\Domains\Rides\Support\Enums\PaymentMethod;
use App\Domains\Rides\Support\Enums\RideClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestRideRequest extends FormRequest
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
            'ride_class' => ['required', Rule::in(RideClass::values())],
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'origin_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dest_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', Rule::in(PaymentMethod::values())],
        ];
    }
}
