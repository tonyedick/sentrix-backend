# Sentrix — Identity, RBAC & Organizations

This backend is a domain-driven modular monolith. Each domain owns its models, HTTP layer, services, events, and routes, and is wired into the app through a single service provider. Cross-domain communication happens through events, never through direct controller-to-controller calls.

## Domain layout

Domains live under `app/Domains/` and autoload through the existing `App\ => app/` PSR-4 mapping, so no `composer.json` change is required.

```
app/
├── Domains/
│   ├── Shared/                      Cross-cutting kernel
│   │   ├── Concerns/HasUuid.php     Ordered-UUID primary keys
│   │   ├── Data/DataTransferObject.php
│   │   └── Providers/DomainServiceProvider.php
│   │
│   ├── Identity/                    Registration, login, logout, "me"
│   │   ├── DTOs/                     RegisterUserData, LoginData
│   │   ├── Events/UserRegistered.php
│   │   ├── Http/{Controllers,Requests,Resources}/
│   │   ├── Services/AuthService.php
│   │   ├── Routes/api.php
│   │   └── Providers/IdentityServiceProvider.php
│   │
│   ├── Authorization/               RBAC: org-scoped roles + global SuperAdmin (Spatie teams)
│   │   ├── Models/{Role,Permission}.php   UUID subclasses
│   │   ├── Support/Enums/{SystemRole,OrganizationRole,DefaultPermission}.php
│   │   ├── Services/RoleService.php
│   │   ├── Http/Middleware/SetOrganizationTeam.php
│   │   ├── Http/{Controllers,Requests,Resources}/
│   │   ├── Database/Seeders/PermissionCatalogueSeeder.php
│   │   ├── Routes/api.php
│   │   └── Providers/AuthorizationServiceProvider.php
│   │
│   └── Organization/                Organizations, members, invitations
│       ├── Models/{Organization,OrganizationMembership,OrganizationInvitation}.php
│       ├── DTOs/                     CreateOrganizationData, InviteMemberData
│       ├── Services/{Organization,Membership,Invitation}Service.php
│       ├── Events/{OrganizationCreated,MemberInvited,MemberJoined,MemberRemoved}.php
│       ├── Listeners/{CreateDefaultOrganization,SendInvitationNotification}.php
│       ├── Notifications/OrganizationInvitationNotification.php
│       ├── Database/Migrations/
│       ├── Http/{Controllers,Requests,Resources}/
│       ├── Routes/api.php
│       └── Providers/OrganizationServiceProvider.php
```

Each `*ServiceProvider` extends `Shared\Providers\DomainServiceProvider`, which loads the domain's `Routes/api.php` (under the `api` middleware group + `api` prefix) and its `Database/Migrations`. The domain providers are registered in `bootstrap/providers.php`: `Identity`, `Authorization`, `Organization`, `Audit`, `Trip`, `Emergency`, `Incident`, `Notification`, `Tracking`.

## Key decisions

**UUID primary keys.** The `HasUuid` trait assigns an ordered (COMB) UUID on the model `creating` event, keeping index locality high under write load. `users` and `personal_access_tokens.tokenable_id` were migrated to UUID, as were all permission, organization, membership, and invitation tables.

**Authentication — dual mode.** `device_name` in the request decides the credential type: present → a Sanctum bearer token is issued (React Native); absent → a stateful SPA session is established (Inertia web). `statefulApi()` is enabled in `bootstrap/app.php` so cookie auth works on `/api/*`. All endpoints sit behind the `sanctum` guard (declared explicitly in `config/auth.php`), which accepts either.

**RBAC — two tiers.** Spatie's *teams* feature is enabled with `team_foreign_key = organization_id`. Permissions are a global catalogue; **organization-scoped** roles (`OrganizationRole`: `OrganizationAdmin`, `Dispatcher`, `Responder`, `User`) bundle them per tenant. The `SetOrganizationTeam` middleware (`organization.team`) resolves the active organization (route param → `X-Organization` header → `user.current_organization_id`), verifies membership (403 otherwise), and calls `setPermissionsTeamId()` so every check resolves against the right tenant. A `Gate::before` hook short-circuits two cases: (1) the **platform-global `SuperAdmin`** (`SystemRole`, stored with `team_id = NULL`) holds every ability everywhere, regardless of org context; (2) an **organization owner** implicitly holds every ability within their own organization.

**Response envelope.** Every `/api/*` JSON response is normalised by the `WrapApiResponse` middleware (on the `api` group) into `{ success, message, data }` (or `{ success, message, errors }` on failure). Pagination `links`/`meta` are preserved. Controllers stay thin and return plain API Resources / paginators.

**Bounded collections.** Every index endpoint paginates via the `Controller::perPage()` helper (`?per_page`, default 15, max 100) — no unbounded `->get()`.

**Event-driven, queue-first.** Registration fires `UserRegistered`; the Organization domain listens and provisions a personal workspace on the queue. Inviting a member fires `MemberInvited`; a listener sends a **queued** notification. No HTTP request blocks on side effects.

**Thin controllers.** Controllers validate via Form Requests, delegate to services, and return API Resources. All multi-step writes (org creation, membership changes, invitation acceptance) are wrapped in DB transactions inside the service layer.

## API surface (prefix `/api`)

```
POST   /v1/auth/register                                  (throttled)
POST   /v1/auth/login                                     (throttled)
POST   /v1/auth/logout                                    auth
GET    /v1/auth/me                                        auth

GET    /v1/organizations                                  auth
POST   /v1/organizations                                  auth
GET    /v1/organizations/{organization}                   auth + team
PATCH  /v1/organizations/{organization}                   organization.update
DELETE /v1/organizations/{organization}                   organization.delete
POST   /v1/organizations/{organization}/switch            auth + team

GET    /v1/organizations/{organization}/members           members.view
PATCH  /v1/organizations/{organization}/members/{user}    members.update
DELETE /v1/organizations/{organization}/members/{user}    members.remove

GET    /v1/organizations/{organization}/invitations       members.view
POST   /v1/organizations/{organization}/invitations       members.invite
DELETE /v1/organizations/{organization}/invitations/{id}  members.invite
POST   /v1/invitations/{token}/accept                     auth

GET    /v1/organizations/{organization}/roles             auth + team
POST   /v1/organizations/{organization}/roles             roles.manage
GET    /v1/organizations/{organization}/roles/{role}      auth + team
PATCH  /v1/organizations/{organization}/roles/{role}      roles.manage
DELETE /v1/organizations/{organization}/roles/{role}      roles.manage
GET    /v1/organizations/{organization}/permissions       auth + team

POST   /v1/auth/forgot-password                           (throttled)
POST   /v1/auth/reset-password                            (throttled)
GET    /v1/auth/email/verify/{id}/{hash}                  signed
POST   /v1/auth/email/verification-notification           auth

GET    /v1/organizations/{organization}/trips             trips.view
POST   /v1/organizations/{organization}/trips             trips.create
GET    /v1/organizations/{organization}/trips/{trip}      trips.view
PATCH  /v1/organizations/{organization}/trips/{trip}      trips.update
POST   .../trips/{trip}/complete                          trips.update
POST   .../trips/{trip}/cancel                            trips.cancel

GET    /v1/organizations/{organization}/emergencies       emergencies.view
POST   /v1/organizations/{organization}/emergencies       emergencies.trigger
GET    .../emergencies/{emergency}                        emergencies.view
POST   .../emergencies/{emergency}/acknowledge            emergencies.acknowledge
POST   .../emergencies/{emergency}/resolve                emergencies.resolve
POST   .../emergencies/{emergency}/cancel                 owner | emergencies.resolve

GET    /v1/organizations/{organization}/incidents         incidents.view
POST   /v1/organizations/{organization}/incidents         incidents.create
GET    .../incidents/{incident}                           incidents.view
PATCH  .../incidents/{incident}                           incidents.update
POST   .../incidents/{incident}/investigate               incidents.update
POST   .../incidents/{incident}/escalate                  incidents.escalate
POST   .../incidents/{incident}/resolve                   incidents.resolve
POST   .../incidents/{incident}/close                     incidents.resolve

GET    /v1/organizations/{organization}/audit-logs        audit.view
```

Default roles per organization: `OrganizationAdmin`, `Dispatcher`, `Responder`, `User`. The organization creator is assigned `OrganizationAdmin` (and, as owner, holds an implicit super-grant within that org). `SuperAdmin` is a platform-global role, not provisioned per organization. All collection responses are paginated and wrapped in the standard envelope.

## Operational domains, realtime & audit

**Operational domains.** `Trip`, `Emergency`, and `Incident` are full vertical slices following the same pattern as the foundation domains (model + migration on UUID keys, DTOs, Form Requests, services-over-controllers, API Resources, events, routes, provider). They form an escalation chain: a `Trip` can raise an `Emergency`, and an `Emergency` can be promoted to an `Incident`. Workflow state lives in enums (`TripStatus`, `EmergencyStatus`, `IncidentStatus`); `IncidentStatus` encodes an explicit transition graph and the service rejects illegal transitions (422). Record-level access (e.g. a field user only seeing their own trips/emergencies) is enforced in the controllers; coarse abilities live in the permission catalogue.

**Realtime (Reverb).** Operational events broadcast to the private `organizations.{id}` channel (authorized in `routes/channels.php`). The base classes `Shared\Events\OrganizationBroadcastEvent` and `Shared\Events\OrganizationRecordEvent` keep this DRY: a concrete event typically just declares its dotted `action()` (e.g. `emergency.triggered`) and the base derives the channel, the client-facing `broadcastAs` name, and a compact `broadcastWith` payload. Broadcasts are queued and dispatched **after commit** (`afterCommit = true`) so subscribers never observe rolled-back state. `MemberJoined` is the reference implementation (`member.joined`).

**Audit trail.** The `Audit` domain owns an append-only `audit_logs` table (no `updated_at`). Any event may opt in by implementing `Audit\Contracts\Auditable`; a wildcard listener (`Event::listen('*', …)`) records one row per dispatched `Auditable` event. Writes are **synchronous and in-transaction**, so a committed action always has its audit row and a rolled-back one has none. `OrganizationRecordEvent` implements `Auditable`, so every operational event is audited for free. Read access is `GET …/audit-logs` (`audit.view`).

**Email verification & password reset.** `User` implements `MustVerifyEmail`; a queued listener sends the verification link on `UserRegistered`. Verification uses a stateless signed route (signature + email hash) so it works for web and mobile; reset links point at the front-end (`APP_FRONTEND_URL`) and a successful reset revokes all of the user's API tokens. Notification URLs are configured in `IdentityServiceProvider`.

**Auth hardening.** Login is case-insensitive and **enumeration-resistant**: a missing account runs an equivalent bcrypt operation and returns the identical `422`, so neither timing nor response body reveals whether an email is registered (`AuthService::verifyCredentials`). Credential endpoints use a layered throttle — 5/min per (email + IP) plus 20/min per IP — that resists brute force without enabling targeted account-lockout DoS. `Password::defaults()` enforces ≥12 chars with mixed case, numbers, symbols and a breach check in production (relaxed to ≥8 elsewhere). Sanctum token lifetime is env-driven (`SANCTUM_EXPIRATION`, non-expiring by default). Covered by `AuthSecurityTest`. **Email-verification gating** is applied to administrative **writes** only (org update/delete, member role update/remove, invitation create/revoke, role create/update/delete) via the `verified` middleware; reads, organization create/switch, invitation acceptance, and every life-safety action stay open so a person in distress is never blocked by an unverified email.

**RBAC hardening.** Authorization is defended against privilege escalation by `Authorization\Services\PermissionGuard`: an actor may only grant a permission set (or assign a role) that is a subset of their own effective permissions — the owner and `SuperAdmin` bypass it. Role names matching a default or system role are rejected at creation (they would otherwise hijack the existing role via `findOrCreate`); default roles cannot be deleted or renamed (permissions may still be tuned). Because `{role}` binds by id globally, every single-role action asserts the role belongs to the active organization (404 otherwise), preventing cross-tenant access and reaching the NULL-team `SuperAdmin` role. Permission validation is pinned to the `web` guard, and the owner's own role is immutable. Covered by `RbacSecurityTest` and `VerificationGatingTest`.

**Organization lifecycle.** `slug` is unique at the database level; `OrganizationService` retries create/update when a concurrent insert wins the slug race (the retry re-derives the suffix from the now-committed row), so the in-app uniqueness check can't be defeated under concurrency. Ownership is transferable (`POST …/transfer-ownership`, owner/SuperAdmin only): the new owner — who must already be a member — is granted `OrganizationAdmin`, and the former owner becomes an ordinary member who can then be removed. This is the supported path for an owner to leave, since the owner is otherwise immovable (`MembershipService::removeMember` refuses to remove them) — which also guarantees an organization always retains an effective admin. Soft-deleting an organization clears the `current_organization_id` of any member scoped to it (avoiding a dangling pointer to a tenant that now resolves to 404) and emits an audited `OrganizationDeleted`; the team middleware's `findOrFail` already makes a soft-deleted tenant unreachable, so switching into one is impossible. Covered by `OrganizationLifecycleTest`.

**Operational concurrency & escalation.** Every operational state transition (trip complete/cancel/overdue, emergency acknowledge/resolve/cancel, incident transitions) runs inside a transaction that `lockForUpdate`s and re-reads the row before mutating, so concurrent responders can't double-acknowledge or lose an update — the loser sees the committed state and is rejected. The domains form an automatic escalation pipeline: a scheduled sweep (`trips:flag-overdue`, every minute, `withoutOverlapping`) flags overdue trips, which auto-raises an emergency (`RaiseEmergencyForOverdueTrip`); a `critical` emergency auto-opens a linked incident (`OpenIncidentForCriticalEmergency`). Both auto-actions are idempotent (one live emergency per trip; one incident per emergency), run as queued listeners on the `critical` queue, and are individually toggleable via `config/sentrix.php`. The Redis queue is configured `after_commit`, so a worker never acts on a row before its transaction commits. Coordinates are validated as complete lat/lng pairs. Covered by `EscalationChainTest` and `OperationalGuardsTest`.

**Schema integrity.** A dedicated migration hardens the database itself: explicit indexes on every foreign key (PostgreSQL does not auto-index FKs), `CHECK` constraints pinning every `status`/`severity` column to its enum's values (so an invalid state can't exist even via raw SQL), and partial indexes for the hot "live records per organization" boards and the overdue sweep. CHECK + partial indexes are PostgreSQL-specific (driver-guarded); FK indexes are portable. Covered by `SchemaIntegrityTest`. See `docs/database-conventions.md`.

**Notifications.** The `Notification` domain fans operational events out to the right responders. Cross-domain listeners on `EmergencyTriggered` and `IncidentEscalated` use `ResponderResolver` to find the organization members holding the relevant ability (`emergencies.acknowledge` / `incidents.escalate`) — excluding whoever raised the event — and send a queued, resilient notification over Laravel's standard channels: `mail`, `database` (the in-app feed, on a UUID-morphed `notifications` table), and the recipient's private `broadcast` channel. The active channel set is config-driven (`SENTRIX_NOTIFICATION_CHANNELS`); SMS/push slot in by implementing a channel + `to{Channel}()` without touching the listeners. Delivery is intentionally at-least-once (a duplicate alert beats a missed one). The in-app feed is exposed under `/api/v1/notifications`, strictly scoped to the caller. Covered by `NotificationTest`.

**Location tracking (Phase 1 — data plane).** The `Tracking` domain captures a trip's live position with an **asymmetric** design suited to poor networks: the device buffers fixes locally and **batch-uploads over HTTP** (store-and-forward), while dispatchers will consume a coalesced websocket stream (Phase 2). Ingestion is **idempotent** — each fix carries a device-assigned id, and `trip_locations` has a unique `(client_fix_id, recorded_at)`, so the retries a flaky network guarantees never duplicate. The track is an append-only, **monthly RANGE-partitioned** table (with a DEFAULT catch-all so an insert can't fail on a missing partition; a scheduled `tracking:ensure-partitions` rolls the window forward) — no FK on the hot path, with `organization_id`/`user_id` denormalised for tenant-scoped reads. The **authoritative last-known position lives on the trip row** (durable + queryable for the staleness sweep), and only advances to a genuinely newer fix so a late buffered batch can't rewind it. Only the trip's own user may ingest, while the trip is live; reads are the per-trip history and an operator-only org-wide snapshot (`/locations/latest`). **Phase 2 — fan-out:** on ingest, the newest position is broadcast to the org channel as `trip.location`, **coalesced** to ≤1 per trip per `SENTRIX_LOCATION_BROADCAST_COALESCE` seconds via an atomic Redis lock (`Cache::add`, SET-NX-EX) — so a burst of fixes can't flood the websocket layer. The event is broadcast-only (not audited — position telemetry isn't a state change) and carries scalars (no model) to keep the hot path light. Covered by `LocationTrackingTest` and `LocationBroadcastTest`. **Phase 3 — staleness → escalation:** a `lost_contact_at` flag gives exactly-once "gone dark" detection. A scheduled sweep (`tracking:flag-stale`, every minute) atomically flags active trips whose last fix is older than `SENTRIX_LOCATION_STALE_AFTER` and fires `TripLostContact`, which the escalation chain turns into a "lost contact" emergency (via the generalised `EmergencyService::raiseForTrip`, idempotent — overdue + dark = one emergency). Ingesting a fix clears the flag and fires `TripReconnected`, re-arming detection. Covered by `StalenessEscalationTest`. **Phase 4 — PostGIS proximity:** the Sail Postgres image is `postgis/postgis:17-3.5` (PostGIS has no stable Postgres 18 image yet); a migration enables the extension and adds generated `geography(Point,4326)` columns (`trips.last_location`, `trip_locations.location`) with GiST indexes. `ProximityService` answers "active trips within N metres of a point" via `ST_DWithin` (index-accelerated) + `ST_Distance` (geography → metres), exposed as `GET …/locations/nearby` and `GET …/emergencies/{emergency}/nearby-trips` (operator-only; the latter excludes the emergency's own trip). Covered by `ProximityTest` (PostGIS-guarded). *Geofence zones (org polygons + containment + breach→escalation) are the next slice on the same geography columns.*

**Infrastructure (Redis + Horizon).** `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` run on **Redis** (see `.env`), so the queue-first design is supervised by Horizon and broadcasts/notifications/audit-free side effects scale operationally. Redis and Reverb already run in the Sail `compose.yaml`.

**Queue resilience.** Queued listeners extend `Shared\Listeners\QueuedListener`, which centralises the retry policy `docs/event-system.md` mandates: bounded `tries` with exponential-ish `backoff()`, a `maxExceptions` circuit-breaker, a per-attempt `timeout`, and a `failed()` hook that logs context (no silent failures). Handlers are written to be idempotent so a retry never duplicates work (e.g. `CreateDefaultOrganization` no-ops if the user already has a workspace). The queued invitation notification carries the same policy.

**Broadcast priority.** Time-critical events override `broadcastQueue = 'critical'` (every `Emergency*` event and `IncidentEscalated`); the Horizon supervisor processes `['critical', 'default']` so life-safety broadcasts are drained ahead of routine traffic. Routine events (trips, membership, incident open/resolve/close) stay on `default`. Private channel authorization is exercised by `ChannelAuthorizationTest` against `/broadcasting/auth`.

> **Deploy note.** New permissions are registered by the catalogue seeder. After deploying, run `./vendor/bin/sail artisan db:seed --class="App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder"` before provisioning new organizations so role provisioning can attach the new abilities.

## Running it

All commands run through Laravel Sail (Docker):

```bash
./vendor/bin/sail composer dump-autoload   # pick up the App\Domains\* classes
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail test
```

The seeder creates a platform `SuperAdmin` (`admin@sentrix.test`) and a demo user (`test@example.com`) owning an "Acme Inc" organization with the full default role set. Because workspace provisioning and invitation emails are queued, run a worker (`./vendor/bin/sail artisan queue:work` / Horizon) in non-test environments.

## Extending

Add a new domain by creating `app/Domains/<Name>/` with a provider extending `DomainServiceProvider`, then register it in `bootstrap/providers.php`. Keep new tables on UUID keys via the `HasUuid` trait, expose them through Form Requests + API Resources, and emit events for anything another domain might care about.
```
