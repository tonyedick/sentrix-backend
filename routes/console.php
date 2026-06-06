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
