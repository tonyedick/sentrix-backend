<?php

declare(strict_types=1);

namespace App\Domains\Organization\Events;

use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrganizationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Organization $organization,
    ) {}
}
