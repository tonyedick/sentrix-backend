<?php

declare(strict_types=1);

namespace App\Domains\Organization\DTOs;

use App\Domains\Organization\Http\Requests\InviteMemberRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class InviteMemberData extends DataTransferObject
{
    public function __construct(
        public readonly string $email,
        public readonly string $role,
    ) {}

    public static function fromRequest(InviteMemberRequest $request): self
    {
        /** @var array{email:string,role:string} $data */
        $data = $request->validated();

        return new self(
            email: mb_strtolower($data['email']),
            role: $data['role'],
        );
    }
}
