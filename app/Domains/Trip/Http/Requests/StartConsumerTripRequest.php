<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Consumer trip start (user-scoped, ADR-0001). The monitored user is always the
 * authenticated user; the serving org is resolved server-side.
 */
final class StartConsumerTripRequest extends FormRequest
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
            'origin_label' => ['sometimes', 'nullable', 'string', 'max:255'],
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
