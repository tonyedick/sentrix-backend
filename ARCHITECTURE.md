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

Each `*ServiceProvider` extends `Shared\Providers\DomainServiceProvider`, which loads the domain's `Routes/api.php` (under the `api` middleware group + `api` prefix) and its `Database/Migrations`. The three domain providers are registered in `bootstrap/providers.php`.

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
```

Default roles per organization: `OrganizationAdmin`, `Dispatcher`, `Responder`, `User`. The organization creator is assigned `OrganizationAdmin` (and, as owner, holds an implicit super-grant within that org). `SuperAdmin` is a platform-global role, not provisioned per organization. All collection responses are paginated and wrapped in the standard envelope.

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
