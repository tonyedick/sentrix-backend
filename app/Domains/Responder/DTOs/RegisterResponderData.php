<?php

declare(strict_types=1);

namespace App\Domains\Responder\DTOs;

use App\Domains\Responder\Http\Requests\RegisterResponderRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class RegisterResponderData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $userId,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromRequest(RegisterResponderRequest $request): self
    {
        return new self(
            userId: $request->string('user_id')->value(),
            metadata: $request->input('metadata'),
        );
    }
}
