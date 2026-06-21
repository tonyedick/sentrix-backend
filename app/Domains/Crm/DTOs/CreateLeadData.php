<?php

declare(strict_types=1);

namespace App\Domains\Crm\DTOs;

use App\Domains\Crm\Support\Enums\ClientType;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class CreateLeadData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly ClientType $clientType,
        public readonly string $contactName,
        public readonly string $contactEmail,
        public readonly ?string $contactPhone = null,
        public readonly ?string $region = null,
        public readonly ?string $source = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->trim()->value(),
            clientType: ClientType::from($request->string('client_type', ClientType::Other->value)->value()),
            contactName: $request->string('contact_name')->trim()->value(),
            contactEmail: $request->string('contact_email')->trim()->lower()->value(),
            contactPhone: $request->input('contact_phone'),
            region: $request->input('region'),
            source: $request->input('source'),
            notes: $request->input('notes'),
        );
    }
}
