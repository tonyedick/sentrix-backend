<?php

declare(strict_types=1);

namespace App\Domains\Organization\Providers;

use App\Domains\Identity\Events\UserRegistered;
use App\Domains\Organization\Events\MemberInvited;
use App\Domains\Organization\Listeners\CreateDefaultOrganization;
use App\Domains\Organization\Listeners\SendInvitationNotification;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class OrganizationServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();
        $this->registerListeners();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }

    private function registerListeners(): void
    {
        // Cross-domain: Auth fires UserRegistered, Organization provisions a workspace.
        Event::listen(UserRegistered::class, CreateDefaultOrganization::class);
        Event::listen(MemberInvited::class, SendInvitationNotification::class);
    }
}
