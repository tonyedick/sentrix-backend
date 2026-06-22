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
        'official_ttl_seconds' => (int) env('SENTRIX_ALERT_OFFICIAL_TTL', 86400), // 24h
        'dismiss_threshold' => (int) env('SENTRIX_ALERT_DISMISS_THRESHOLD', 3),
        // Trust-weighted verification thresholds over the signed confidence tally.
        'confirm_threshold' => (int) env('SENTRIX_ALERT_CONFIRM_THRESHOLD', 3),
        'dispute_threshold' => (int) env('SENTRIX_ALERT_DISPUTE_THRESHOLD', -2),
        'default_radius_m' => (int) env('SENTRIX_ALERT_RADIUS', 3000),
        'safe_places_radius_m' => (int) env('SENTRIX_SAFE_PLACES_RADIUS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Places / POI directory (consumer)
    |--------------------------------------------------------------------------
    */

    'places' => [
        'default_radius_m' => (int) env('SENTRIX_PLACES_RADIUS', 5000),

        // Google Maps Platform key for the server-side geocoding proxies
        // (autocomplete / geocode / nearby). SERVER-SIDE ONLY — never shipped to
        // the client. When UNSET (tests, local), every proxy endpoint falls back
        // to a deterministic curated result so the surface always answers offline.
        'google_api_key' => env('SENTRIX_PLACES_GOOGLE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rewards / gamification (consumer)
    |--------------------------------------------------------------------------
    |
    | Points economy + gamification catalogues. Badges, missions, and premium
    | packs are code/config-driven (no per-badge tables) — earned/progress state
    | is DERIVED from the reward account + ledger at read time. `counter` maps a
    | milestone to a derived metric (reports filed, trips completed, streak days,
    | lifetime points). Points are integers; premium packs convert points → days.
    |
    */

    'rewards' => [
        // Leaderboard window: how many top users to surface (the caller's rank is
        // always included even when outside the window).
        'leaderboard_size' => (int) env('SENTRIX_REWARDS_LEADERBOARD_SIZE', 20),

        // Badge catalogue. `counter` is a derived metric:
        //   reports     — earn ledger entries reasoned 'report'/'verified_report'
        //   trips        — earn ledger entries reasoned 'safe_trip'
        //   verifications— earn ledger entries reasoned 'verify_alert'
        //   streak_days  — current daily streak on the account
        //   lifetime_points — sum of positive ledger movements
        'badges' => [
            ['id' => 'first_trip', 'name' => 'First Steps', 'description' => 'Complete your first safe trip', 'counter' => 'trips', 'target' => 1],
            ['id' => 'road_warrior', 'name' => 'Road Warrior', 'description' => 'Complete 10 safe trips', 'counter' => 'trips', 'target' => 10],
            ['id' => 'community_hero', 'name' => 'Community Hero', 'description' => 'File 10 community reports', 'counter' => 'reports', 'target' => 10],
            ['id' => 'guardian_eye', 'name' => 'Guardian Eye', 'description' => 'Verify 25 nearby alerts', 'counter' => 'verifications', 'target' => 25],
            ['id' => 'on_a_roll', 'name' => 'On a Roll', 'description' => 'Keep a 7-day safety streak', 'counter' => 'streak_days', 'target' => 7],
            ['id' => 'point_collector', 'name' => 'Point Collector', 'description' => 'Earn 1000 lifetime points', 'counter' => 'lifetime_points', 'target' => 1000],
        ],

        // Daily/weekly mission catalogue. Progress derives from ledger activity
        // within the rolling window (daily = today, weekly = last 7 days).
        'missions' => [
            ['id' => 'd_checkin', 'scope' => 'daily', 'title' => 'Open Sentrix and check in', 'points' => 10, 'counter' => 'checkin', 'target' => 1],
            ['id' => 'd_verify2', 'scope' => 'daily', 'title' => 'Verify 2 nearby alerts', 'points' => 30, 'counter' => 'verify_alert', 'target' => 2],
            ['id' => 'd_safetrip', 'scope' => 'daily', 'title' => 'Complete a safe trip', 'points' => 50, 'counter' => 'safe_trip', 'target' => 1],
            ['id' => 'w_trips3', 'scope' => 'weekly', 'title' => 'Finish 3 monitored trips', 'points' => 150, 'counter' => 'safe_trip', 'target' => 3],
            ['id' => 'w_report2', 'scope' => 'weekly', 'title' => 'Help with 2 community reports', 'points' => 90, 'counter' => 'report', 'target' => 2],
        ],

        // Points → Premium conversion packs. `cost` is integer points; `days` is
        // the Premium entitlement granted.
        'premium_packs' => [
            'pp3' => ['days' => 3, 'cost' => 300],
            'pp7' => ['days' => 7, 'cost' => 600],
            'pp30' => ['days' => 30, 'cost' => 2000],
        ],
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

        // PSP checkout: shared HMAC secret used to sign + verify webhooks
        // (X-Sentrix-Signature = HMAC-SHA256 of the RAW body). When UNSET the
        // webhook is closed by default in production; the LogPaymentProvider only
        // accepts unsigned webhooks in local/testing. `allow_simulated_checkout`
        // opens the sandbox simulate endpoint outside local/testing (never in prod
        // unless explicitly set).
        'webhook_secret' => env('SENTRIX_BILLING_WEBHOOK_SECRET'),
        'allow_simulated_checkout' => (bool) env('SENTRIX_BILLING_ALLOW_SIMULATED_CHECKOUT', false),

        // Multi-region catalog. The plan price book above is authored in base
        // minor units; each region localizes it via `rate` (an FX multiplier over
        // the base price_cents), bills in `currency`, and adds a `tax_rate` line.
        // Default region is NG. Amounts stay INTEGER CENTS at every step.
        'regions' => [
            'NG' => ['currency' => 'NGN', 'rate' => (float) env('SENTRIX_BILLING_RATE_NG', 1.0), 'tax_rate' => (float) env('SENTRIX_BILLING_TAX_NG', 0.075)],
            'KE' => ['currency' => 'KES', 'rate' => (float) env('SENTRIX_BILLING_RATE_KE', 0.30), 'tax_rate' => (float) env('SENTRIX_BILLING_TAX_KE', 0.16)],
            'US' => ['currency' => 'USD', 'rate' => (float) env('SENTRIX_BILLING_RATE_US', 0.0023), 'tax_rate' => (float) env('SENTRIX_BILLING_TAX_US', 0.0)],
        ],
        'default_region' => env('SENTRIX_BILLING_DEFAULT_REGION', 'NG'),

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
