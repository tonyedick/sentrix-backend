<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use App\Domains\Identity\Http\Requests\LoginRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class LoginData extends DataTransferObject
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false,
        public readonly ?string $deviceName = null,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        /** @var array{email:string,password:string,remember?:bool,device_name?:string} $data */
        $data = $request->validated();

        return new self(
            email: $data['email'],
            password: $data['password'],
            remember: (bool) ($data['remember'] ?? false),
            deviceName: $data['device_name'] ?? null,
        );
    }

    /**
     * Token-based auth (React Native) is requested when a device name is sent;
     * otherwise we fall back to stateful SPA cookie auth (Inertia web).
     */
    public function wantsToken(): bool
    {
        return $this->deviceName !== null;
    }

    /**
     * @return array{email:string,password:string}
     */
    public function credentials(): array
    {
        return ['email' => $this->email, 'password' => $this->password];
    }
}
