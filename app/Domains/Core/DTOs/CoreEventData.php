<?php

declare(strict_types=1);

namespace App\Domains\Core\DTOs;

use Illuminate\Http\Request;

/**
 * The event payload posted to /api/v1/core/events by a product/detection.
 *
 * Mirrors the ecosystem schema in SENTRIX_INTEGRATION.md:
 *   { type, source, severity, summary, org, site?, zone?, subjects?[],
 *     location?{lat,lng}, payload?{} }
 */
final readonly class CoreEventData
{
    /**
     * @param  list<string>  $subjects
     * @param  array{lat?: float|int|null, lng?: float|int|null}|null  $location
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public string $source,
        public string $severity,
        public string $summary,
        public string $org,
        public ?string $site,
        public ?string $zone,
        public array $subjects,
        public ?array $location,
        public array $payload,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<int, string> $subjects */
        $subjects = array_values(array_filter(
            (array) $request->input('subjects', []),
            static fn (mixed $v): bool => is_string($v) && $v !== '',
        ));

        $location = $request->input('location');

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->input('payload', []);

        return new self(
            type: $request->string('type')->trim()->value(),
            source: $request->string('source')->trim()->value(),
            severity: $request->string('severity')->trim()->value(),
            summary: $request->string('summary')->trim()->value(),
            org: $request->string('org')->trim()->value(),
            site: self::nullableString($request->input('site')),
            zone: self::nullableString($request->input('zone')),
            subjects: $subjects,
            location: is_array($location) ? $location : null,
            payload: $payload,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
