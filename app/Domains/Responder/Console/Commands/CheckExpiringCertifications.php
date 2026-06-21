<?php

declare(strict_types=1);

namespace App\Domains\Responder\Console\Commands;

use App\Domains\Responder\Services\ResponderCapabilityService;
use Illuminate\Console\Command;

/**
 * Sweeps responder certifications: warns on those approaching expiry and lapses
 * those past it. Scheduled daily; idempotent and transaction-wrapped, so it is
 * safe to run concurrently or re-run.
 */
final class CheckExpiringCertifications extends Command
{
    protected $signature = 'responders:check-certifications';

    protected $description = 'Warn on expiring and lapse expired responder certifications.';

    public function handle(ResponderCapabilityService $capabilities): int
    {
        ['expiring' => $expiring, 'expired' => $expired] = $capabilities->sweepExpiry();

        $this->info("Certifications swept: {$expiring} expiring, {$expired} expired.");

        return self::SUCCESS;
    }
}
