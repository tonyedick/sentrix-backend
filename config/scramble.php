<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Scramble — auto-generated OpenAPI 3 docs for the Sentrix API
|--------------------------------------------------------------------------
|
| Scramble derives the full API spec from the code itself — routes, Form
| Requests (request bodies + validation), and API Resources (response shapes)
| — so every endpoint across the domains is documented with no annotations.
| Only plain values are set here so this file is safe to merge with whatever
| Scramble version is installed.
|
| After `composer require dedoc/scramble`:
|   - Interactive docs:  GET /docs/api
|   - OpenAPI 3 spec:     GET /docs/api.json   (import this into Postman for the
|                                              complete, auto-generated collection)
|   - Export to file:     php artisan scramble:export   (writes api.json)
|
*/

return [
    // Document everything under the `api` mount and KEEP the version segment
    // visible in every path (e.g. `/v1/auth/login`, `/v1/organizations/...`).
    // Every route in this app already lives under `v1/`, so nothing un-versioned
    // leaks in. The server URL therefore resolves to `{host}/api` and full URLs
    // read `{host}/api/v1/...`.
    'api_path' => 'api',

    'api_domain' => null,

    // Spec is exported to the project root when you run `php artisan scramble:export`.
    'export_path' => 'api.json',

    'ui' => [
        // Document title shown in the docs header and the generated OpenAPI `info.title`.
        'title' => 'Sentrix API — Safety & Security Platform',
    ],

    'info' => [
        'version' => '1.0.0',
        'description' => <<<'MD'
            **Sentrix** is the canonical backend for the Sentrix safety & security platform — one
            versioned, multi-tenant API (`/api/v1`) that consolidates personal safety, organizational
            security operations, a security-command/CAD layer, evidence & intelligence, and a safe
            mobility marketplace, built for the Nigerian market. The **SentrixCore** AI agent is a
            separate Python service plugged in over HTTP; this API exposes the bridge to it.

            Every endpoint is versioned under `/api/v1`. Responses use a consistent envelope —
            `{ success, message, data }` on success and `{ success, message, errors }` on failure.

            ## Authentication
            Laravel Sanctum **bearer tokens**. API clients (mobile, Postman) send a `device_name`
            on register/login to receive a token, then pass `Authorization: Bearer <token>`.
            Org-scoped routes sit under `/api/v1/organizations/{organization}/…`, consumer routes
            under `/api/v1/me/…`, and platform-staff routes under `/api/v1/{ledger,leads,command,rides/admin}`.

            ## Who it serves (stakeholders)
            - **Citizens / consumers** — mobile users protecting themselves and their families: trips,
              live tracking, SOS/emergencies, Safe Rides, community safe-places, and rewards.
            - **Organizations / security operators** — security firms, estates, and corporates running
              incident, emergency, and dispatch operations over their own tenant, responders, cameras,
              hardware, and insurance.
            - **Responders / field agents** — receive dispatch and taskings, manage duty shifts and skills.
            - **Security command & agencies (CAD)** — command hierarchy with GPS routing, CAD units,
              BOLOs, and mutual aid for coordinated, multi-agency response.
            - **Platform staff (Sentrix internal)** — operate the financial Ledger, CRM (lead → activation),
              Rides-Ops, and command oversight; gated on elevated platform roles.
            - **Partners & integrators** — consume signed webhooks, billing/PSP, and the SentrixCore bridge.

            ## Key capabilities (what each area does)
            - **Identity & Access** — registration/login (device-scoped Sanctum tokens), email
              verification, account & NDPR data export, saved locations, safety contacts, push tokens.
            - **Organizations, RBAC & Audit** — multi-tenant organizations, members & invitations,
              roles & granular permissions, ownership transfer, and an immutable audit trail.
            - **Incidents, Emergencies & Dispatch** — incident lifecycle, SOS/emergency triggers,
              assignment & dispatch to responders, escalation, and notifications.
            - **Trips & Tracking** — trip plans, live location tracking, and overdue/staleness sweeps.
            - **Responders** — onboarding, duty shifts, skills, and availability.
            - **VisionGuard** — camera registry and media capture.
            - **Security Command / CAD** — agencies, command hierarchy + GPS unit routing, CAD
              dispatch, BOLOs, mutual aid, unit comms, command analytics, and taskings.
            - **Evidence, Retention & Intel** — forensic evidence vault with search, tiered retention
              lifecycle, and intelligence reports/analytics (with CSV export).
            - **Ingest / Signals pipeline** — detection → decision → incident automation, vision /
              SafeSignal ingest, and an anonymized public safety feed.
            - **Safe Rides** — ride lifecycle with in-ride safety, driver onboarding & vetting,
              wallet & payments (Sentrix Send), the ride marketplace, surge, and an ops dashboard.
            - **Places** — server-side geocode, autocomplete, and category nearby trip-planning proxies.
            - **Community & Rewards** — trust-voted safe places and gamified safety engagement.
            - **Billing & Webhooks** — subscriptions and PSP checkout, plus signed partner webhook delivery.
            - **SentrixCore bridge** — proxy to the Python AI agent: chat (SSE), act, machine event
              ingress, and the command-center view. Degrades gracefully when Core is offline.

            > Conventions: UUID identifiers, services-over-controllers with DB transactions, Form-Request
            > RBAC, API-Resource response shapes, and broadcast + audited domain events.
            MD,
    ],

    // Leave null to use the current host (works behind `sail share` too). To pin
    // explicit servers, set e.g. [['url' => 'https://api.sentrix.example']].
    'servers' => null,
];
