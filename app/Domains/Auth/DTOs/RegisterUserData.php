<?php

declare(strict_types=1);

namespace App\Domains\Auth\DTOs;

use App\Domains\Auth\Http\Requests\RegisterRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class RegisterUserData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $deviceName = null,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        /** @var array{name:string,email:string,password:string,device_name?:string} $data */
        $data = $request->validated();

        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            deviceName: $data['device_name'] ?? null,
        );
    }

    public function wantsToken(): bool
    {
        return $this->deviceName !== null;
    }
}
