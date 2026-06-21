<?php

declare(strict_types=1);

namespace App\Domains\Crm\DTOs;

use App\Domains\Crm\Support\Enums\LeadStage;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Patch for a lead's stage, notes, and contact details. Every field is optional;
 * {@see toAttributes} maps only the keys actually present on the request so a
 * partial PATCH never clobbers untouched columns.
 */
final class UpdateLeadData extends DataTransferObject
{
    public function __construct(
        public readonly ?LeadStage $stage,
        public readonly bool $notesProvided,
        public readonly ?string $notes,
        public readonly ?string $contactName,
        public readonly ?string $contactEmail,
        public readonly bool $contactPhoneProvided,
        public readonly ?string $contactPhone,
        public readonly ?string $region,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            stage: $request->filled('stage')
                ? LeadStage::from($request->string('stage')->value())
                : null,
            notesProvided: $request->exists('notes'),
            notes: $request->input('notes'),
            contactName: $request->filled('contact_name') ? $request->string('contact_name')->trim()->value() : null,
            contactEmail: $request->filled('contact_email') ? $request->string('contact_email')->trim()->lower()->value() : null,
            contactPhoneProvided: $request->exists('contact_phone'),
            contactPhone: $request->input('contact_phone'),
            region: $request->filled('region') ? $request->string('region')->trim()->value() : null,
        );
    }

    /**
     * Map the present fields to a column => value array for a partial update.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attributes = [];

        if ($this->stage !== null) {
            $attributes['stage'] = $this->stage;
        }

        if ($this->notesProvided) {
            $attributes['notes'] = $this->notes;
        }

        if ($this->contactName !== null) {
            $attributes['contact_name'] = $this->contactName;
        }

        if ($this->contactEmail !== null) {
            $attributes['contact_email'] = $this->contactEmail;
        }

        if ($this->contactPhoneProvided) {
            $attributes['contact_phone'] = $this->contactPhone;
        }

        if ($this->region !== null) {
            $attributes['region'] = $this->region;
        }

        return $attributes;
    }
}
