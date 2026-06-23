# Specification — Make `GET /api/v1/auth/me` organization-aware

**Status:** proposed (specification only — no code changed)
**Owner:** Backend (Identity + Authorization domains)
**Consumers affected:** `sentrix-dashboard` (web), `sentrix-mobile` (future)
**Related:** `sentrix-dashboard/docs/SHELL.md` (§Authorization finding), Spatie
laravel-permission *teams* feature.

---

## 1. Current behavior

`GET /api/v1/auth/me` returns the authenticated principal.

- **Route:** `app/Domains/Identity/Routes/api.php` —
  `Route::get('me', [CurrentUserController::class, 'show'])` inside the
  `prefix('v1/auth')->middleware('auth:sanctum')` group. It carries **`auth:sanctum` only**.
- **Controller:** `CurrentUserController::show()` eager-loads
  `['currentOrganization', 'organizations', 'roles']` and returns `UserResource`.
- **Resource:** `UserResource` emits, among other fields:
  - `roles` → `$this->getRoleNames()` (when the `roles` relation is loaded);
  - `permissions` → `$this->getAllPermissions()->pluck('name')` (only when the
    request has `?with_permissions=1`);
  - `current_organization_id`, `current_organization`, `organizations`.
- **Envelope:** wrapped by `WrapApiResponse` as `{ success, message, data }`.

Authorization uses **Spatie laravel-permission with `teams = true`**
(`config/permission.php`). Role/permission grants are scoped to an organization
("team") via `PermissionRegistrar::setPermissionsTeamId()`. The
`SetOrganizationTeam` middleware (alias `organization.team`) sets that team id per
request, resolving it in this order: route `{organization}` → `X-Organization`
header → the user's `current_organization_id`.

## 2. Current limitation

The `auth/me` route is **not** behind `organization.team`, and nothing else sets
the team id for that request. So when `UserResource` runs, the
`PermissionRegistrar` team id is **null**.

Consequence with teams enabled:
- `getRoleNames()` and `getAllPermissions()` resolve against **team_id = null**,
  i.e. only **globally-scoped** grants.
- **SuperAdmin** (a global `SystemRole`, team-independent) appears correctly.
- **Organization-scoped roles** (`OrganizationRole`: `OrganizationAdmin`,
  `Dispatcher`, `Responder`, `User`) and their permissions are **assigned with a
  team id**, so they come back **empty** in `/auth/me`.

Net effect: for a normal org member, `/auth/me` reports `roles: []` and
`permissions: []` even though they hold real org-scoped grants. Any consumer that
gates UI on `/auth/me` permissions cannot do so accurately for org users.

## 3. Required backend changes

Make `/auth/me` resolve and apply an organization team context **before** the
resource reads roles/permissions. The mechanism already exists
(`SetOrganizationTeam`); the change is to apply it to this route and let the
caller select the org.

**3.1 Org selection (input) — additive, backward compatible**
Resolve the effective organization for the call in this precedence:
1. `?organization={id}` query parameter (explicit), **or** `X-Organization` header;
2. the user's `current_organization_id` (default);
3. none → behave as today (global scope).

Guard: a non-SuperAdmin requesting an org they don't belong to → `403`
(reuse `SetOrganizationTeam`'s existing membership check). SuperAdmin may target
any org.

**3.2 Apply team context to the route**
Preferred: a thin, **non-aborting** team-resolver applied to `auth/me` (and
sensible to also apply to other `v1/me` self endpoints). Two viable options:

- **Option A (recommended): reuse `SetOrganizationTeam` on the route.** Add the
  `organization.team` middleware to the `auth/me` route. It already falls back to
  `current_organization_id` and enforces membership. Lowest-risk, no new code,
  consistent with the rest of the app.
- **Option B: resolve inside `CurrentUserController`.** Call
  `PermissionRegistrar::setPermissionsTeamId($orgId)` before building the
  resource. More explicit but duplicates middleware logic — avoid unless A is
  unsuitable.

> Edge case for Option A: `SetOrganizationTeam` currently `findOrFail`s the
> organization and binds `organization.current`. For a user with **no**
> organization (`current_organization_id = null` and no header), it must
> no-op gracefully (it already returns early when no org id resolves). Confirm
> that path returns global scope rather than 404.

**3.3 Resource output — make scope explicit**
`UserResource` should make the active scope unambiguous so clients don't guess:
- add `current_organization_id` already present — keep;
- add a field indicating the **scope the roles/permissions were computed for**,
  e.g. `roles_organization_id` (the team id used), so the dashboard can cache
  per-org and detect mismatches;
- keep `roles` / `permissions` semantics, now correctly team-scoped;
- (optional, recommended) when `?with_permissions=1`, the permissions list now
  reflects the resolved org — document this in the OpenAPI description.

**3.4 Backward compatibility**
- No parameter ⇒ default to `current_organization_id` ⇒ org users now get their
  real org grants (a *fix*, not a breaking change in shape).
- Response shape only **adds** fields; existing fields keep their meaning.
- If `current_organization_id` is null and no override, behavior is unchanged
  (global scope) — existing clients unaffected.

## 4. Impact on teams / scoped permissions

- The change funnels `/auth/me` through the same team-scoping every org-scoped
  endpoint already uses, so role/permission resolution becomes **consistent
  across the API** (no special-case for `/auth/me`).
- **SuperAdmin** remains global allow-all via `Gate::before`; unaffected.
- **Multi-org users:** `?organization=` / `X-Organization` lets a client read
  grants for a specific membership; default remains `current_organization_id`.
- **Membership enforcement** is preserved (403 for non-members), so `/auth/me`
  cannot be used to probe foreign-org grants.
- No change to how grants are **assigned** (`MembershipService`,
  `PermissionCatalogueSeeder`) — only how they're **read** for this endpoint.

## 5. Impact on dashboard authorization

- The dashboard already sends `X-Organization` (from `OrganizationContext`) on
  authenticated calls and already calls `/auth/me?with_permissions=1`.
- Once `/auth/me` is org-aware, `principal.permissions` / `principal.roles`
  become **accurate per org**, so:
  - remove the temporary `orgMemberFallback` flags in
    `sentrix-dashboard/src/authz/access.ts` usages → navigation becomes **exact
    per-permission** (success criterion fully met without coarse fallback);
  - `OrganizationContext.switchOrganization()` → `refresh()` will now return the
    new org's grants, so the shell re-scopes on switch automatically;
  - `RequirePermission` route guards tighten to real permissions.
- No dashboard API-shape changes required; only the (documented) removal of the
  fallback flag and optional use of `roles_organization_id` for cache keys.

## 6. Impact on mobile applications

- Mobile uses Sanctum **bearer tokens** (`device_name` on login) and calls
  `/auth/me`. With the default (`current_organization_id`) behavior, mobile gets
  the user's grants for their active org **without any client change** — a
  strict improvement (previously empty for org users).
- Mobile may optionally send `X-Organization` to read a specific org's grants
  (e.g. an org picker), mirroring the dashboard.
- No breaking change: response only gains fields; existing parsing keeps working.
- Token issuance/expiry untouched (separate from this spec).

## 7. Migration strategy

Phased, non-breaking:

1. **Backend (additive):** implement §3 behind the default
   (`current_organization_id`) so the shape only **adds** `roles_organization_id`
   and corrects (populates) `roles`/`permissions`. Ship with OpenAPI/Scramble
   docs updated. No client is required to change.
2. **Verify in staging:** confirm SuperAdmin, single-org, multi-org, and no-org
   users (tests in §8) against a seeded dataset.
3. **Dashboard rollout:** once staging confirms accurate org grants, remove the
   `orgMemberFallback` flags and (optionally) adopt `roles_organization_id` for
   per-org caching. Deploy dashboard after backend.
4. **Mobile:** no action required; optionally adopt `X-Organization` for an org
   switcher later.
5. **Rollback:** revert is safe — removing the middleware/param restores global
   scope; only the dashboard fallback removal would need re-adding (keep that
   dashboard change in its own commit/flag for easy revert).

No API version bump needed (additive + bugfix semantics). Coordinate deploy
order: **backend first, dashboard second.**

## 8. Required tests

Feature tests (separate `testing` DB, RefreshDatabase + `PermissionCatalogueSeeder`):

**Scoping correctness**
1. Org member with an `OrganizationAdmin` role + `current_organization_id` set →
   `GET /auth/me?with_permissions=1` returns that org's `roles` and
   `permissions` (non-empty) and `roles_organization_id == current org`.
2. Same user, **no** override → defaults to `current_organization_id`
   (regression guard for the default path).
3. Member of org A and org B → `?organization=B` returns B's grants;
   `?organization=A` returns A's; default returns the current org's.
4. `X-Organization` header is honored equivalently to the query param, and
   query param takes precedence when both are present (define + assert order).

**Authorization / safety**
5. Non-member requesting `?organization={foreign}` → `403` (membership guard).
6. SuperAdmin requesting any org → `200` with that org's scope; SuperAdmin with
   no override → global allow-all semantics preserved (`isSuperAdmin` true).
7. User with **no** organization and no override → `200`, `roles: []`,
   `permissions: []`, `roles_organization_id: null` (graceful global, no 404).

**Shape / compatibility**
8. Response still matches the envelope and includes all pre-existing fields;
   `roles_organization_id` is present; `permissions` only included with
   `?with_permissions=1` (unchanged trigger).
9. `currentAccessToken` / bearer path unaffected (mobile token client gets the
   same scoped result as a stateful client for the same org).

**Docs**
10. Scramble/OpenAPI for `auth/me` documents the new `organization` parameter,
    the `X-Organization` header, and `roles_organization_id`.

---

### Appendix — affected symbols (for the implementer)
- `app/Domains/Identity/Routes/api.php` (the `auth/me` route)
- `app/Domains/Identity/Http/Controllers/CurrentUserController.php`
- `app/Domains/Identity/Http/Resources/UserResource.php`
- `app/Domains/Authorization/Http/Middleware/SetOrganizationTeam.php` (reused)
- `app/Domains/Authorization/Support/Enums/{SystemRole,OrganizationRole,DefaultPermission}.php`
- `config/permission.php` (`teams => true`)
- Dashboard follow-up: `sentrix-dashboard/src/authz/access.ts` (drop `orgMemberFallback`)
