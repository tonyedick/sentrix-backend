<?php

declare(strict_types=1);

namespace App\Domains\Ingest\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A SafeSignal cross-product, life-safety report posted to
 * /api/v1/signal/ingest.
 *
 *   { organization_id (uuid OR org slug), product, type, severity?, summary,
 *     site?, zone?, subjects?[], lat?, lng?, payload?{} }
 *
 * Because SafeSignal is life-safety, an explicit severity (if given) is honored
 * verbatim; otherwise the DecisionEngine assesses the type.
 */
final class IngestSignalData extends DataTransferObject
{
    /**
     * @param  list<string>  $subjects
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $organization,
        public readonly string $product,
        public readonly string $type,
        public readonly ?string $severity,
        public readonly string $summary,
        public readonly ?string $site,
        public readonly ?string $zone,
        public readonly array $subjects,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly array $payload,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $subjects */
        $subjects = array_values(array_filter(
            (array) $request->input('subjects', []),
            static fn (mixed $v): bool => is_string($v) && $v !== '',
        ));

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->input('payload', []);

        // Accept either organization_id or org as the tenant reference (uuid or slug).
        $organization = $request->input('organization_id', $request->input('org'));

        return new self(
            organization: is_string($organization) ? trim($organization) : '',
            product: $request->string('product')->trim()->value(),
            type: $request->string('type')->trim()->value(),
            severity: self::nullableString($request->input('severity')),
            summary: $request->string('summary')->trim()->value(),
            site: self::nullableString($request->input('site')),
            zone: self::nullableString($request->input('zone')),
            subjects: $subjects,
            lat: $request->has('lat') ? (float) $request->input('lat') : null,
            lng: $request->has('lng') ? (float) $request->input('lng') : null,
            payload: $payload,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
