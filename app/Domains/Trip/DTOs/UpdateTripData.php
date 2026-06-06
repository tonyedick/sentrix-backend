<?php

declare(strict_types=1);

namespace App\Domains\Trip\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use App\Domains\Trip\Http\Requests\UpdateTripRequest;
use Illuminate\Support\Carbon;

/**
 * Mutable trip fields. Only keys present in the request are carried through;
 * everything defaults to null and is filtered out before persisting.
 */
final class UpdateTripData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $destinationLabel = null,
        public readonly ?float $destinationLat = null,
        public readonly ?float $destinationLng = null,
        public readonly ?Carbon $expectedArrivalAt = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(UpdateTripRequest $request): self
    {
        return new self(
            destinationLabel: $request->input('destination_label'),
            destinationLat: $request->has('destination_lat') ? (float) $request->input('destination_lat') : null,
            destinationLng: $request->has('destination_lng') ? (float) $request->input('destination_lng') : null,
            expectedArrivalAt: $request->filled('expected_arrival_at')
                ? Carbon::parse((string) $request->input('expected_arrival_at'))
                : null,
            notes: $request->input('notes'),
        );
    }

    /**
     * Map to column => value, dropping nulls so a PATCH only touches sent fields.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'destination_label' => $this->destinationLabel,
            'destination_lat' => $this->destinationLat,
            'destination_lng' => $this->destinationLng,
            'expected_arrival_at' => $this->expectedArrivalAt,
            'notes' => $this->notes,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
