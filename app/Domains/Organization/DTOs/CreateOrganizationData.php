<?php

declare(strict_types=1);

namespace App\Domains\Organization\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use App\Models\User;

final class CreateOrganizationData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly User $owner,
        public readonly ?string $slug = null,
    ) {}
}
