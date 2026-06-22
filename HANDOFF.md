# Sentrix Backend — Handoff

The canonical Sentrix API: a Laravel 13 domain-driven modular monolith (~34 domains under
`app/Domains/`), running on PHP 8.4, Postgres + PostGIS, Redis, and Reverb via Laravel Sail.
It consolidates the full Sentrix feature surface (the old `Sentrix---Omni` Node backend and
`SentrixGoBackend` FastAPI) behind one `/api/v1` contract. **SentrixCore stays a separate
Python service** and is plugged in over HTTP (see §6).

Full test suite: **338 passing, 0 skipped** (`./vendor/bin/sail test`).

---

## 1. Prerequisites
- Docker Desktop (with this folder shared in Settings → Resources → File Sharing).
- That's it — Sail provides PHP/Postgres/Redis/Reverb in containers.

## 2. Stand it up
```bash
cp .env.example .env                      # if you don't have a .env yet
./vendor/bin/sail up -d                   # boots app + Postgres(PostGIS) + Redis + Reverb + Mailpit
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed   # REQUIRED: migrates + seeds the dev DB
./vendor/bin/sail artisan queue:work             # (or Horizon) for queued jobs/notifications/webhooks
```
> The dev database starts empty — `migrate:fresh --seed` is step one. Tests use a separate
> `testing` database that `RefreshDatabase` migrates automatically, so the suite does not need this.

App: `http://localhost`  ·  Mailpit (outgoing mail): `http://localhost:8025`

## 3. Demo logins (seeded)
All seeded users use password **`password`**.

| User | Email | Role |
|---|---|---|
| Platform admin | `admin@sentrix.test` | `SuperAdmin` (platform-global) |
| Demo org owner | `test@example.com` | `OrganizationAdmin` of **Acme Inc** |

`admin@sentrix.test` is the gate for the platform-scoped surfaces (Ledger, CRM, Security
Command/CAD, Rides-Ops). `test@example.com` owns a tenant with the full operational data set.

## 4. Auth & API basics
- Base path: `/api/v1`. Every response is wrapped `{ success, message, data }` (errors:
  `{ success, message, errors }`).
- **API clients (mobile/Postman): send `device_name` on register/login to get a Sanctum bearer
  token.** Then `Authorization: Bearer <token>`.
- Org-scoped routes live under `/api/v1/organizations/{organization}/…`; consumer routes under
  `/api/v1/me/…`; platform-staff routes (SuperAdmin) under `/api/v1/{ledger,leads,command,rides/admin}`.

## 5. Try it / share it
- **Full API docs (all ~305 endpoints, auto-generated):** `./vendor/bin/sail composer require dedoc/scramble`,
  then browse `http://localhost/docs/api`. The OpenAPI spec at `http://localhost/docs/api.json`
  imports into Postman to produce the complete collection with request/response schemas. (Docs are
  open in `local`; gate them via Scramble's `viewApiDocs` Gate before any public deploy.)
- **Postman (quick sampler):** `Sentrix.postman_collection.json` (repo root) covers the main flows —
  set `base_url`, run **Auth → Login (demo user)** (`test@example.com` / `password`) to capture a
  token, then **Organizations → List** to capture the org id. For full coverage, import the OpenAPI
  spec above instead.
- **Share with a teammate:** `./vendor/bin/sail share` prints a public HTTPS tunnel URL — point
  Postman's `base_url` at it. (Tunnel = your dev box; keep the terminal open, tear down when done.)
- **Tests:** `./vendor/bin/sail test` (full) or `--filter=<TestName>`.

## 6. SentrixCore (Python agent) — wiring it in
The Laravel `Core` domain proxies to the Python service; set these to go live (leave unset to run
offline — chat degrades gracefully, never errors):
```
SENTRIX_CORE_ENDPOINT=http://host.docker.internal:8100
SENTRIX_CORE_API_KEY=<shared service token>
```
Endpoints: `POST /api/v1/core/chat` (SSE proxy), `POST /api/v1/core/act`, `POST /api/v1/core/events`
(machine ingress, `X-Service-Token`), `GET /api/v1/core/command-center`.

## 7. What's inside (coverage)
See `docs/SENTRIX_COVERAGE_MATRIX.md` for the full mapping. In brief:
- **Identity/Orgs/RBAC/Audit**, **Trips/Tracking/Emergencies/Incidents/Dispatch/Responders/Escalation/Notifications**, **VisionGuard** (cameras/media).
- **Omni business layer:** Access (visitor passes & gate), Hardware, Insurance, Ledger, CRM.
- **Security Command / CAD:** agencies, command hierarchy + GPS routing, CAD units/dispatch/BOLOs, mutual aid, unit comms, command analytics, duty/taskings.
- **Evidence / Retention / Intel:** forensic vault + search, tiered lifecycle sweep, reports/analytics.
- **Ingest pipeline:** detection→decision→Incident, vision/SafeSignal ingest, anonymized public feed.
- **Safe Rides:** ride lifecycle + in-ride safety, driver onboarding/vetting, wallet & payments, marketplace + Sentrix Send, ops dashboard.
- **Consumer:** Community (trust-voting/safe-places), Places (+geocode), Rewards (gamification), Billing (subscriptions + PSP checkout), Partner Webhooks.

Architecture conventions: `docs/ARCHITECTURE.md` (UUID keys, services-over-controllers, Form-Request
RBAC, API Resources, broadcast+audited events, queue-first).

## 8. Known follow-ups (none block running the app)
1. **Real PSP credentials** — Billing checkout uses a log/sandbox driver; add Paystack/Flutterwave keys + `SENTRIX_BILLING_WEBHOOK_SECRET` to go live.
2. **Fleet driver-score** — stubbed (no Fleet backend yet); insurance UBI + driver vetting read a null score until wired.
3. **Platform-staff RBAC layer** — Ledger / CRM / Command / Rides-Ops are currently gated on `SuperAdmin`. Introduce platform-staff roles (sales, onboarding_manager, dispatch_coordinator, …) to restore fine-grained access + the CRM sell→activate separation of duties.
4. **Mobile app** — when the `sentrix-mobile` (Expo) repo is available, repoint its API layer to this backend's `/api/v1` + Sanctum tokens.
5. **SentrixCore deploy** — run the Python service and set the env in §6.

## 9. Notes
- Sail is pinned to **PHP 8.4** (`compose.yaml`) — the version the code targets (`composer.json: ^8.3`). Do not run on 8.5 for this handoff.
- Scheduled jobs (overdue-trip sweep, tracking staleness, ledger dead-man, retention tiering) run via the scheduler — ensure `schedule:work`/cron and a queue worker are running in any long-lived environment.
