<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,

    // Domain modules
    App\Domains\Auth\Providers\AuthDomainServiceProvider::class,
    App\Domains\Authorization\Providers\AuthorizationServiceProvider::class,
    App\Domains\Organization\Providers\OrganizationServiceProvider::class,
];
