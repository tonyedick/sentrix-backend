<?php

declare(strict_types=1);

namespace App\Domains\Command\DTOs;

use App\Domains\Command\Support\Enums\AgencyStatus;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class CreateAgencyData extends DataTransferObject
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $country,
        public readonly array $categories,
        public readonly ?string $hotline = null,
        public readonly ?string $color = null,
        public readonly ?string $logoUrl = null,
        public readonly AgencyStatus $status = AgencyStatus::Active,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $categories */
        $categories = array_values($request->input('categories', []));

        return new self(
            code: strtoupper($request->string('code')->trim()->value()),
            name: $request->string('name')->trim()->value(),
            country: strtoupper($request->string('country', 'NG')->trim()->value()),
            categories: $categories,
            hotline: $request->filled('hotline') ? $request->string('hotline')->trim()->value() : null,
            color: $request->filled('color') ? $request->string('color')->trim()->value() : null,
            logoUrl: $request->filled('logo_url') ? $request->string('logo_url')->trim()->value() : null,
            status: AgencyStatus::from($request->string('status', AgencyStatus::Active->value)->value()),
        );
    }
}
