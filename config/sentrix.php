<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Escalation chain
    |--------------------------------------------------------------------------
    |
    | Toggles for the automatic safety-escalation pipeline. Both are on by
    | default; either can be disabled per environment.
    |
    |  - auto_emergency_on_overdue_trip: when a trip is flagged overdue, raise an
    |    emergency for the monitored user (idempotent — one live emergency per trip).
    |  - auto_incident_for_critical_emergency: when an emergency is triggered at
    |    `critical` severity, open a linked incident (idempotent per emergency).
    |
    */

    'escalation' => [
        'auto_emergency_on_overdue_trip' => (bool) env('SENTRIX_AUTO_EMERGENCY_ON_OVERDUE', true),
        'auto_incident_for_critical_emergency' => (bool) env('SENTRIX_AUTO_INCIDENT_FOR_CRITICAL', true),
        'auto_emergency_on_lost_contact' => (bool) env('SENTRIX_AUTO_EMERGENCY_ON_LOST_CONTACT', true),

        // Escalation Engine defaults. Per-organization escalation_policies rows
        // override these; the resolver falls back to them when no policy exists.
        'incident_unassigned_seconds' => (int) env('SENTRIX_ESCALATE_INCIDENT_UNASSIGNED', 300),
        'assignment_unaccepted_seconds' => (int) env('SENTRIX_ESCALATE_ASSIGNMENT_UNACCEPTED', 120),
        'responder_no_progression_seconds' => (int) env('SENTRIX_ESCALATE_RESPONDER_NO_PROGRESSION', 600),
        // Opt-in: escalation activates per organization via an escalation_policies
        // row (or by setting these env flags). No policy + flag off ⇒ no scheduling.
        'incident_escalation_enabled' => (bool) env('SENTRIX_ESCALATE_INCIDENT_ENABLED', false),
        'assignment_escalation_enabled' => (bool) env('SENTRIX_ESCALATE_ASSIGNMENT_ENABLED', false),
        'responder_escalation_enabled' => (bool) env('SENTRIX_ESCALATE_RESPONDER_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational notifications
    |--------------------------------------------------------------------------
    |
    | Channels used to notify responders of operational events. Only the
    | channels implemented by the notification classes take effect
    | (`mail`, `database`, `broadcast`); listing an unimplemented channel here is
    | ignored until a corresponding channel + `to{Channel}()` method is added
    | (e.g. SMS via Vonage, push via FCM).
    |
    */

    'notifications' => [
        // Default enabled channels when an organization has no notification_policy.
        // Friendly names: mail (email), database (in-app), broadcast (realtime),
        // sms, push.
        'channels' => array_values(array_filter(
            explode(',', (string) env('SENTRIX_NOTIFICATION_CHANNELS', 'mail,database,broadcast'))
        )),

        // Dedicated Horizon queue for notification delivery.
        'queue' => env('SENTRIX_NOTIFICATIONS_QUEUE', 'notifications'),

        // Provider selection (provider switching) — bound in NotificationServiceProvider.
        // 'log' providers are the safe default (no external calls); swap to a real
        // provider (e.g. twilio/vonage, fcm) without touching channels or notifications.
        'sms' => ['provider' => env('SENTRIX_SMS_PROVIDER', 'log')],
        'push' => ['provider' => env('SENTRIX_PUSH_PROVIDER', 'log')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Location tracking
    |--------------------------------------------------------------------------
    |
    | broadcast_coalesce_seconds: live position updates are coalesced to at most
    | one broadcast per trip per this many seconds, so a burst of ingested fixes
    | can't overwhelm the websocket layer or dashboards. Set 0 to broadcast every
    | batch (not recommended in production).
    |
    */

    'tracking' => [
        'broadcast_coalesce_seconds' => (int) env('SENTRIX_LOCATION_BROADCAST_COALESCE', 2),

        // An active trip whose last fix is older than this is considered to have
        // gone dark — the staleness sweep flags it and (by default) escalates.
        'stale_after_seconds' => (int) env('SENTRIX_LOCATION_STALE_AFTER', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Responders
    |--------------------------------------------------------------------------
    |
    |  - certification_expiry_warning_days: how far ahead the certification sweep
    |    warns a responder that a credential is about to lapse.
    |  - assignment_acceptance_timeout_seconds: how long a dispatched assignment
    |    may sit unaccepted before it expires and is re-offered/escalated.
    |  - location_broadcast_coalesce_seconds: responder live-position updates are
    |    coalesced to at most one broadcast per responder per this many seconds.
    |
    */

    'responders' => [
        'certification_expiry_warning_days' => (int) env('SENTRIX_RESPONDER_CERT_WARN_DAYS', 30),
        'assignment_acceptance_timeout_seconds' => (int) env('SENTRIX_RESPONDER_ACCEPT_TIMEOUT', 120),
        'location_broadcast_coalesce_seconds' => (int) env('SENTRIX_RESPONDER_LOCATION_COALESCE', 5),

        // AI-assisted dispatch is ADVISORY: when enabled, opening an incident or
        // triggering an emergency produces a ranked responder shortlist
        // (ResponderAssignmentRecommended) for a dispatcher to confirm. It never
        // auto-dispatches. Off by default.
        'ai_dispatch_enabled' => (bool) env('SENTRIX_RESPONDER_AI_DISPATCH', false),
        'ai_dispatch_shortlist_size' => (int) env('SENTRIX_RESPONDER_AI_SHORTLIST', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assignment dispatch (Increment 2)
    |--------------------------------------------------------------------------
    |
    |  - auto_reassign: on a decline/timeout/stand-down of a still-needed role,
    |    automatically offer the next-best candidate.
    |  - max_reassign_attempts: after this many failed attempts to fill a role,
    |    escalate instead of re-offering.
    |  - connectivity_stale_after_seconds: a committed responder whose last fix is
    |    older than this is treated as having lost connectivity; the reconciliation
    |    sweep stands the line down and re-offers.
    |  - auto_dispatch: when an assignment is opened with dispatch_mode=auto, the
    |    dispatch job offers the recommended primary (+ required supporting).
    |
    */

    'assignments' => [
        'auto_reassign' => (bool) env('SENTRIX_ASSIGNMENT_AUTO_REASSIGN', true),
        'max_reassign_attempts' => (int) env('SENTRIX_ASSIGNMENT_MAX_REASSIGN', 3),
        'connectivity_stale_after_seconds' => (int) env('SENTRIX_ASSIGNMENT_CONNECTIVITY_STALE_AFTER', 180),
        'auto_dispatch' => (bool) env('SENTRIX_ASSIGNMENT_AUTO_DISPATCH', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer monitoring (serving organization)
    |--------------------------------------------------------------------------
    |
    | Sentrix operates its own monitoring center (see docs/adr-0001-consumer-
    | tenancy.md). A consumer's emergencies / overdue trips are *served by* this
    | organization without the consumer being a member of it. The
    | ServingOrganizationResolver resolves the org for a consumer event:
    |
    |  - default_organization_id: explicit id (takes precedence if set).
    |  - slug/name/owner_email: identity used by MonitoringOrganizationSeeder to
    |    provision the canonical monitoring org and by the resolver to find it.
    |
    | v1 is region-agnostic (always the default org); v2 will choose by coverage
    | area from the event's coordinates, falling back to the default.
    |
    */

    'monitoring' => [
        'default_organization_id' => env('SENTRIX_MONITORING_ORG_ID'),
        'slug' => env('SENTRIX_MONITORING_SLUG', 'sentrix-monitoring'),
        'name' => env('SENTRIX_MONITORING_NAME', 'Sentrix Monitoring'),
        'owner_email' => env('SENTRIX_MONITORING_OWNER_EMAIL', 'monitoring@sentrix.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer auth (OTP)
    |--------------------------------------------------------------------------
    |
    | One-time codes for mobile email/phone verification. Codes are numeric,
    | hashed at rest, single-live-per-channel, and attempt-capped.
    |
    */

    'auth' => [
        'otp' => [
            'length' => (int) env('SENTRIX_OTP_LENGTH', 6),
            'ttl_seconds' => (int) env('SENTRIX_OTP_TTL', 180),
            'max_attempts' => (int) env('SENTRIX_OTP_MAX_ATTEMPTS', 5),
        ],
        // Social sign-in verifier driver: 'stub' (dev/test) → 'apple'/'google'.
        'social' => [
            'driver' => env('SENTRIX_SOCIAL_DRIVER', 'stub'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Community alerts (consumer)
    |--------------------------------------------------------------------------
    |
    | Crowdsourced geo alerts. Alerts auto-expire after the TTL; once
    | dismiss_threshold distinct users dismiss an alert it is resolved.
    |
    */

    'community' => [
        'alert_ttl_seconds' => (int) env('SENTRIX_ALERT_TTL', 21600), // 6h
        'dismiss_threshold' => (int) env('SENTRIX_ALERT_DISMISS_THRESHOLD', 3),
        'default_radius_m' => (int) env('SENTRIX_ALERT_RADIUS', 3000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Places / POI directory (consumer)
    |--------------------------------------------------------------------------
    */

    'places' => [
        'default_radius_m' => (int) env('SENTRIX_PLACES_RADIUS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing (consumer trips)
    |--------------------------------------------------------------------------
    |
    | Route planning for the Trips screen. The built-in 'haversine' driver gives
    | distance/ETA estimates; corridor risk is scored from active community
    | alerts. Swap `driver` for a real routing engine later.
    |
    */

    'routing' => [
        'driver' => env('SENTRIX_ROUTING_DRIVER', 'haversine'),
        'assumed_speed_kmh' => (float) env('SENTRIX_ROUTING_SPEED_KMH', 40),
        'corridor_radius_m' => (int) env('SENTRIX_ROUTING_CORRIDOR_M', 1500),
        'alert_penalty' => (int) env('SENTRIX_ROUTING_ALERT_PENALTY', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing & subscriptions (consumer)
    |--------------------------------------------------------------------------
    |
    | Plan catalogue + entitlements. Prices are in minor units (cents). The
    | payment 'driver' is a provider abstraction — 'log' is a no-op stub for
    | dev/test; swap for a real processor (Paystack / Flutterwave / Stripe).
    | `entitlements` are feature flags gated by the active plan.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Vision Guard (devices & media)
    |--------------------------------------------------------------------------
    |
    | Camera sources + captured media. The storage 'driver' is an abstraction —
    | 'local' is a dev/test stub; swap for S3/GCS (encrypted, plan-based
    | retention) in production.
    |
    */

    'visionguard' => [
        'storage_driver' => env('SENTRIX_MEDIA_STORAGE_DRIVER', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sentrix Core (external AI agent bridge)
    |--------------------------------------------------------------------------
    |
    | Connection to the external Python "SentrixCore" agent. This is a thin,
    | fail-safe HTTP bridge — the Core service is a trusted-network dependency,
    | never a hard dependency: when `endpoint` is empty (or a call fails) every
    | Core-backed Laravel request degrades gracefully instead of 500-ing.
    |
    |  - endpoint: base URL of the Core service (e.g. http://localhost:8100).
    |    Empty ⇒ the bridge serves offline/simulated fallbacks.
    |  - api_key: the shared service token. Forwarded to Core as `X-Service-Token`
    |    on chat/act, AND used to authenticate inbound /core/events posts
    |    (constant-time compare in AuthenticateCoreService). Empty ⇒ inbound
    |    /core/events is closed by default (open only in local/testing).
    |  - timeout: per-request timeout (seconds) for the non-streaming /act call.
    |    The streaming /chat call uses no read timeout (long-lived SSE).
    |
    */

    'core' => [
        'endpoint' => env('SENTRIX_CORE_ENDPOINT'),
        'api_key' => env('SENTRIX_CORE_API_KEY'),
        'timeout' => (int) env('SENTRIX_CORE_TIMEOUT', 30),
    ],

    'billing' => [
        'payment_driver' => env('SENTRIX_PAYMENT_DRIVER', 'log'),
        'currency' => env('SENTRIX_BILLING_CURRENCY', 'USD'),
        'plans' => [
            'free' => [
                'name' => 'Free',
                'price_cents' => 0,
                'interval' => 'none',
                'popular' => false,
                'entitlements' => ['incident_reporting', 'location_sharing', 'live_alerts'],
            ],
            'premium_monthly' => [
                'name' => 'Premium Monthly',
                'price_cents' => 499,
                'interval' => 'month',
                'popular' => false,
                'entitlements' => ['incident_reporting', 'location_sharing', 'live_alerts', 'smart_routing', 'emergency_tools', 'ai_summaries', 'cloud_recording'],
            ],
            'premium_annual' => [
                'name' => 'Premium Annual',
                'price_cents' => 3999,
                'interval' => 'year',
                'popular' => true,
                'entitlements' => ['incident_reporting', 'location_sharing', 'live_alerts', 'smart_routing', 'emergency_tools', 'ai_summaries', 'cloud_recording', 'vision_guard'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage retention (Evidence vault lifecycle)
    |--------------------------------------------------------------------------
    |
    | Per-subscription-plan storage lifecycle policy for the Evidence vault's
    | observations. Mirrors the Sentrix Omni retention engine (lib/retention.js).
    | The Retention domain ages each non-held, non-sealed observation through the
    | cumulative tier windows by the age of `observed_at`:
    |
    |   <= hot_days                        -> hot
    |   <= hot_days + warm_days            -> warm
    |   <= hot_days + warm_days + cold_days -> cold
    |   older                              -> stays cold (archive-eligible)
    |
    | Tiers, evidence retention, and quota are read PER PLAN. An organization's
    | plan is resolved from the Organization model's `plan` attribute when present;
    | the Organization model has no `plan` column today, so resolution falls back
    | to `default_plan` for every org (see RetentionPolicy::forOrganization).
    |
    |  - hot_days / warm_days / cold_days: cumulative aging windows (days).
    |  - evidence_days: how long protected (critical/legal-hold) evidence is kept.
    |  - quota_gb: the per-plan storage quota the usage rollup compares against.
    |
    */

    'retention' => [
        'default_plan' => env('SENTRIX_RETENTION_DEFAULT_PLAN', 'business'),

        'plans' => [
            'community' => [
                'hot_days' => 7,
                'warm_days' => 0,
                'cold_days' => 0,
                'evidence_days' => 30,
                'quota_gb' => 50,
            ],
            'business' => [
                'hot_days' => 30,
                'warm_days' => 60,
                'cold_days' => 0,
                'evidence_days' => 365,
                'quota_gb' => 2048,
            ],
            'enterprise' => [
                'hot_days' => 90,
                'warm_days' => 180,
                'cold_days' => 365,
                'evidence_days' => 2555,
                'quota_gb' => 20480,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Rides — wallet & payments
    |--------------------------------------------------------------------------
    |
    | Consumer-scoped (ADR-0001) wallet, top-up, charge and referrals. All
    | amounts are INTEGER CENTS — never floats. The referral reward is the
    | fixed credit applied to BOTH the referrer and the claimer when a code is
    | claimed (default NGN 1,000 each = 100000 cents).
    |
    */

    'rides' => [
        'referral_reward_cents' => (int) env('SENTRIX_RIDES_REFERRAL_REWARD_CENTS', 100000),
    ],

];
