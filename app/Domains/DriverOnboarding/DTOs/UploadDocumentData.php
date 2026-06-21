<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\DTOs;

use App\Domains\DriverOnboarding\Support\Enums\DocumentType;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * One driver document upload: its type and a (signed) storage URL.
 */
final class UploadDocumentData extends DataTransferObject
{
    public function __construct(
        public readonly DocumentType $type,
        public readonly string $url,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            type: DocumentType::from($request->string('type')->value()),
            url: $request->string('url')->trim()->value(),
        );
    }
}
