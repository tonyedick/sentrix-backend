<?php

declare(strict_types=1);

namespace App\Domains\Cad\Events;

use App\Domains\Cad\Models\Bolo;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A BOLO / officer-safety broadcast was issued down a command. Plain platform
 * event (NOT an organization record event) — the CAD layer is national/
 * cross-tenant.
 */
final class BoloIssued
{
    use Dispatchable;

    public function __construct(
        public readonly Bolo $bolo,
        public readonly ?string $actorId = null,
    ) {}
}
