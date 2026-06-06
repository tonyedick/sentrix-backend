<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,

    // Domain modules
    App\Domains\Identity\Providers\IdentityServiceProvider::class,
    App\Domains\Authorization\Providers\AuthorizationServiceProvider::class,
    App\Domains\Organization\Providers\OrganizationServiceProvider::class,
    App\Domains\Audit\Providers\AuditServiceProvider::class,
    App\Domains\Trip\Providers\TripServiceProvider::class,
    App\Domains\Emergency\Providers\EmergencyServiceProvider::class,
    App\Domains\Incident\Providers\IncidentServiceProvider::class,
    App\Domains\Notification\Providers\NotificationServiceProvider::class,
    App\Domains\Tracking\Providers\TrackingServiceProvider::class,
];
