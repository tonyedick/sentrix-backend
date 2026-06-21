<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flag overdue trips every minute. withoutOverlapping() prevents a slow sweep
// from stacking; markOverdue is idempotent and row-locked, so this is safe.
Schedule::command('trips:flag-overdue')
    ->everyMinute()
    ->withoutOverlapping();

// Provision next month's trip_locations partition ahead of time.
Schedule::command('tracking:ensure-partitions')
    ->monthlyOn(20, '03:00')
    ->withoutOverlapping();

// Flag trips whose device has gone dark and escalate. Idempotent + row-atomic.
Schedule::command('tracking:flag-stale')
    ->everyMinute()
    ->withoutOverlapping();

// Warn on expiring and lapse expired responder certifications. Idempotent.
Schedule::command('responders:check-certifications')
    ->daily()
    ->withoutOverlapping();

// Activate/close responder duty shifts at their boundaries. Idempotent.
Schedule::command('responders:process-duty')
    ->everyMinute()
    ->withoutOverlapping();

// Escalate assignments past their acceptance deadline. Idempotent.
Schedule::command('assignments:escalate-overdue')
    ->everyMinute()
    ->withoutOverlapping();

// Stand down + reassign responders that have lost connectivity. Idempotent.
Schedule::command('assignments:reconcile-connectivity')
    ->everyMinute()
    ->withoutOverlapping();

// Dead-man switch: flag Ledger sources that have gone silent past the stale
// window (raises one alert per source, re-armed on next write). Idempotent.
Schedule::command('ledger:flag-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Age Evidence observations through the storage tiers (hot->warm->cold) across
// every organization by plan retention windows. Idempotent + row-atomic.
Schedule::command('retention:sweep')
    ->daily()
    ->withoutOverlapping();
