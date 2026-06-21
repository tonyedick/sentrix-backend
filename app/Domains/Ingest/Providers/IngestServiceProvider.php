<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Wires the Ingest detection→decision pipeline: its migrations (the
 * detection_events table + the backward-compatible incidents ALTER) and its
 * routes (ingest/*, signal/ingest, public/incidents).
 *
 * Ingest reuses the globally-registered `core.service` middleware alias owned by
 * the Core bridge for machine-token auth — it does not re-register it.
 */
final class IngestServiceProvider extends DomainServiceProvider
{
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
