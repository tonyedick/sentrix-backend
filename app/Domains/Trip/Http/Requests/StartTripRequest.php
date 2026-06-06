<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

final class StartTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::TripsCreate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional: operators may start a trip for another member.
            'user_id' => ['sometimes', 'uuid'],
            'origin_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Coordinates must be supplied as a complete lat/lng pair. (No
            // `sometimes`: it would skip `required_with` when the field is absent.)
            'origin_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:origin_lng'],
            'origin_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:origin_lat'],
            'destination_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:destination_lng'],
            'destination_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:destination_lat'],
            'expected_arrival_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
