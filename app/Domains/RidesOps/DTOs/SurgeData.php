<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Inbound payload for the surge control. Either PIN a manual surge (multiplier
 * 1.0–3.0, optional zone/note) or RELEASE the current one (release=true).
 */
final class SurgeData extends DataTransferObject
{
    public function __construct(
        public readonly bool $release,
        public readonly ?float $multiplier,
        public readonly ?string $zone,
        public readonly ?string $note,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            release: $request->boolean('release'),
            multiplier: $request->filled('multiplier') ? (float) $request->input('multiplier') : null,
            zone: $request->filled('zone') ? $request->string('zone')->trim()->value() : null,
            note: $request->filled('note') ? $request->string('note')->trim()->value() : null,
        );
    }
}
