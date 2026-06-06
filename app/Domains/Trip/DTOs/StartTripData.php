<?php

declare(strict_types=1);

namespace App\Domains\Trip\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use App\Domains\Trip\Http\Requests\StartTripRequest;
use Illuminate\Support\Carbon;

final class StartTripData extends DataTransferObject
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $originLabel = null,
        public readonly ?float $originLat = null,
        public readonly ?float $originLng = null,
        public readonly ?string $destinationLabel = null,
        public readonly ?float $destinationLat = null,
        public readonly ?float $destinationLng = null,
        public readonly ?Carbon $expectedArrivalAt = null,
        public readonly ?string $notes = null,
    ) {}

    /**
     * Build from a validated request. The monitored user defaults to the actor
     * but operators may start a trip on behalf of another member.
     */
    public static function fromRequest(StartTripRequest $request, string $defaultUserId): self
    {
        return new self(
            userId: $request->has('user_id') ? $request->string('user_id')->value() : $defaultUserId,
            originLabel: $request->input('origin_label'),
            originLat: $request->has('origin_lat') ? (float) $request->input('origin_lat') : null,
            originLng: $request->has('origin_lng') ? (float) $request->input('origin_lng') : null,
            destinationLabel: $request->input('destination_label'),
            destinationLat: $request->has('destination_lat') ? (float) $request->input('destination_lat') : null,
            destinationLng: $request->has('destination_lng') ? (float) $request->input('destination_lng') : null,
            expectedArrivalAt: $request->filled('expected_arrival_at')
                ? Carbon::parse((string) $request->input('expected_arrival_at'))
                : null,
            notes: $request->input('notes'),
        );
    }
}
