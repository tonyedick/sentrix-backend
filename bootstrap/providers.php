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
    App\Domains\Responder\Providers\ResponderServiceProvider::class,
    App\Domains\Assignment\Providers\AssignmentServiceProvider::class,
    App\Domains\Escalation\Providers\EscalationServiceProvider::class,

    // Omni business layer (access management, hardware, CRM, insurance, ledger)
    App\Domains\Access\Providers\AccessServiceProvider::class,
    App\Domains\Hardware\Providers\HardwareServiceProvider::class,
    App\Domains\Insurance\Providers\InsuranceServiceProvider::class,
    App\Domains\Ledger\Providers\LedgerServiceProvider::class,
    App\Domains\Crm\Providers\CrmServiceProvider::class,
    App\Domains\Command\Providers\CommandServiceProvider::class,
    App\Domains\Cad\Providers\CadServiceProvider::class,
    App\Domains\Coordination\Providers\CoordinationServiceProvider::class,
    App\Domains\Core\Providers\CoreServiceProvider::class,
    // Ingest / detection pipeline (detection→decision→incident, SafeSignal, anonymized public feed)
    App\Domains\Ingest\Providers\IngestServiceProvider::class,
    App\Domains\Evidence\Providers\EvidenceServiceProvider::class,
    App\Domains\Retention\Providers\RetentionServiceProvider::class,
    App\Domains\Intel\Providers\IntelServiceProvider::class,
    App\Domains\Webhooks\Providers\WebhooksServiceProvider::class,

    // Consumer modules (ADR-0001)
    App\Domains\Community\Providers\CommunityServiceProvider::class,
    App\Domains\Places\Providers\PlacesServiceProvider::class,
    App\Domains\Rewards\Providers\RewardsServiceProvider::class,
    App\Domains\Billing\Providers\BillingServiceProvider::class,
    App\Domains\Rides\Providers\RidesServiceProvider::class,
    App\Domains\RidesMarket\Providers\RidesMarketServiceProvider::class,
    App\Domains\RidesOps\Providers\RidesOpsServiceProvider::class,
    App\Domains\Wallet\Providers\WalletServiceProvider::class,
    App\Domains\DriverOnboarding\Providers\DriverOnboardingServiceProvider::class,
    App\Domains\VisionGuard\Providers\VisionGuardServiceProvider::class,
];
