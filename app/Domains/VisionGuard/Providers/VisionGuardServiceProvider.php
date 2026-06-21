<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\VisionGuard\Contracts\MediaStorage;
use App\Domains\VisionGuard\Support\LocalMediaStorage;

final class VisionGuardServiceProvider extends DomainServiceProvider
{
    public function register(): void
    {
        // Storage driver: 'local' stub by default; swap for S3/GCS (encrypted,
        // plan-based retention) via config('sentrix.visionguard.storage_driver').
        $this->app->bind(MediaStorage::class, static function (): MediaStorage {
            return match ((string) config('sentrix.visionguard.storage_driver', 'local')) {
                default => new LocalMediaStorage(),
            };
        });
    }

    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
