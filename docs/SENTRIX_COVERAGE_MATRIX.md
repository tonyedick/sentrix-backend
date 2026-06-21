# Sentrix — Laravel Coverage Matrix

The single source of truth for migrating **everything** the team built in
`Sentrix---Omni` (zero-dep Node backend) and `SentrixGoBackend` (FastAPI) into the
canonical Laravel backend (`sentrix-backend`, `/api/v1`).

**Guiding principle (agreed):** `SentrixCore` stays a **Python** service. Laravel does
**not** re-implement the agent; it **plugs into Core over HTTP** (chat/act proxy, event
ingest, capability tools, proactive alert push). See §4.

**Legend:** ✅ done in Laravel · 🟡 partial (core done, gaps listed) · ❌ not yet built ·
🐍 Core (Python, integrate via API).

_Generated 2026-06-18 from a full re-read of both source repos._

---

## 1. ✅ Captured in Laravel today

| Feature area | Source | Laravel domain |
|---|---|---|
| Auth (register/login/OTP/forgot/reset/email-verify/logout/me), push tokens | Omni + Go | `Identity` |
| Emergency contacts, saved locations, recent searches | Go | `Identity` |
| Multi-tenant orgs, members, invitations, ownership transfer | Omni | `Organization` |
| RBAC — permission catalogue, org-scoped roles, SuperAdmin, privilege-escalation guards | Omni | `Authorization` |
| Tamper-evident audit trail | Omni | `Audit` |
| Trips + safe-route planning, complete/cancel | Go | `Trip` |
| Live location ingest, staleness→escalation, PostGIS proximity | Go | `Tracking` |
| SOS / emergencies lifecycle (trigger/ack/resolve/cancel) | Omni + Go | `Emergency` |
| Incident management + workflow + timeline | Omni | `Incident` |
| Dispatch + responder-line workflow (offer/accept/en-route/on-scene/complete) | Omni | `Assignment` |
| Responder registry — skills, certifications, locations, duty shifts | Omni + Go | `Responder` |
| Auto-escalation engine (overdue trip → emergency → incident) | derived | `Escalation` |
| In-app notifications + realtime broadcast (Reverb replaces SSE) | Omni | `Notification` + Reverb |
| Camera/source registry + media assets | Omni | `VisionGuard` |
| Visitor passes & gate (issue/scan/revoke/log, single-use, validity) | Omni | `Access` ⭐new |
| Hardware device registry (register/list/resync/diagnose) | Omni | `Hardware` ⭐new |
| Insurance — risk scoring, quote, policies, claims (file/decide) | Omni + Go | `Insurance` ⭐new |
| Community alert report + feed (geo) | Go | `Community` |
| Places directory search | Go | `Places` |
| Rewards account + ledger + redeem | Go | `Rewards` |
| Subscription plans + subscribe/cancel + invoices | Go | `Billing` |

---

## 2. 🟡 Partial — core exists, gaps to close

| Feature area | What's done | What's missing | Home |
|---|---|---|---|
| **NDPR/GDPR self-service** | change-password (reset flow) | `DELETE /me` account deletion, `GET /me/export` data export, human "Sentrix ID" | `Identity` |
| **Community intelligence** | report, geo feed | trust-weighted **verify/dispute** voting, **resolve**, agency-queue view, staff **publish** (official/AI), **safe-places** directory | `Community` |
| **Places** | curated search | **autocomplete**, **geocode**, **nearby** (server-side Google proxies; key never in app) | `Places` |
| **Rewards/gamification** | points, ledger, redeem | **badges**, **leaderboard**, **missions**, **daily streak**, **points→Premium** convert | `Rewards` |
| **Billing/payments** | plans, sub, invoices | real **PSP checkout + webhook** (Paystack/Flutterwave), sandbox simulate, **multi-region catalog/quote** | `Billing` |
| **Safety (consumer)** | SOS + guarded trips via Emergency/Tracking | covert triggers (code-word/crash/hold), **SOS evidence attach**, check-in/arrive, "watching" (guardian) views, safety settings | `Emergency`/new `Safety` |
| **Insurance** | risk/quote/policies/claims | **claim-draft-from-alert** bridge, **UBI** (pull Fleet driver score) | `Insurance` |
| **Org hierarchy** | Org + members | **Site → Zone → Camera** tiers; per-org **Agencies** (sub-spaces + ingest keys); per-product **entitlements** | `Organization`/`VisionGuard` |
| **Trips** | plan/start/complete | **trip share** + public proof link | `Trip` |

---

## 3. ❌ Missing — net-new domains to build

Ordered by leverage. Each is a fresh domain via the `sentrix-laravel-domain` skill.

### 3.1 Detection → decision → alert pipeline & ingest
The Omni "brain": `POST /events/ingest`, `POST /vision/ingest` (provider-agnostic),
`POST /signal/ingest` (**SafeSignal** cross-product), the `decide()` risk-scoring engine,
alert localization (26 locales), partner **webhooks** (`/api/integrations`), and the
**public safety feed** (`/api/public/incidents`). → new `Ingest` / `Signals` domain feeding `Incident`.

### 3.2 Sentrix Ledger (ecosystem write-spine)
Sources (lifecycle pending→active↔suspended→revoked), `X-Ledger-Key` ingest, write feed,
stats, dead-man/stale switch. → new `Ledger` domain. _(task open)_

### 3.3 CRM lifecycle (sales → onboard → bill)
Leads, stages, quote snapshot, **convert→provision tenant**; separation of duties (sales
cannot convert). → new `Crm` domain. _(task open)_

### 3.4 Security Command / CAD — the largest gap
National agencies, 4-tier command hierarchy, **CAD units** (AVL, status, SLA per
NFPA 1710), incident routing/categorization, **BOLOs**, **mutual aid**, **unit comms**,
command **analytics**, **duty book**, taskings, regions, two-person **broadcast**. From
Omni `seccmd`/`cad`/`mutualaid`/`unitcomms` **and** Go `/api/command/*`. → new
`Command` domain (likely split: `Command`, `Cad`). **Biggest single workstream.**

### 3.5 Safe Rides platform (consumer growth engine)
Ride quote/request/track/complete, in-ride safety (arm/SOS/evidence/check-in), driver
onboarding (KYC → documents → inspection → hardware install → activate), **wallet** +
local-rail top-up + charge, **referrals**, **marketplace** (name-your-price bids),
**Sentrix Send** delivery (COD), ops/admin dashboard, surge. From Go `rides*`. → new
`Rides` domain (large; may split `Rides`, `RidesOps`, `Wallet`).

### 3.6 Evidence vault & forensics
`observe` ingest, metadata index, **NL forensic search**, cross-camera **vehicle/appearance
journey** (ontology), vault stats, legal hold/bookmark. → new `Evidence` domain.

### 3.7 Storage lifecycle & retention
Quota by plan, hot→warm→cold→expire **scheduled sweep**, archive-export (PDF/HTML/CSV),
archive-first purge, legal hold. → new `Retention` domain (+ scheduled command).

### 3.8 Reports & Intel
Period reports (24h/7d/30d), analytics (trends/heatmaps/dwell/zones), formal exports. →
new `Intel` domain.

### 3.9 Smaller items
Tenancy **config** (`/api/config`), **governance/policy** propose→approve, **break-glass**
access, connected **sources** toggle, **verification/KYC** gate, **risk corridors**
(`/api/risk/roads`). → fold into existing or small new domains.

---

## 4. 🐍 SentrixCore integration (keep Python, plug via API)

Core is **not** rebuilt in Laravel. Laravel becomes the product backend + the gateway in
front of Core. Build one `Core` integration domain in Laravel that owns this contract:

- **`POST /api/v1/core/chat`** → proxy (SSE passthrough) to the Python Core `/api/core/chat`,
  forwarding the authenticated principal's scopes via `X-Sentrix-Scopes`.
- **`POST /api/v1/core/act`** → proxy a confirmed, scope-checked tool execution to Core.
- **`POST /api/v1/core/events`** → product/detection events in → workflow + push (Laravel side).
- **Capability tools for Core** → expose Laravel's domains as typed, RBAC-gated tools
  (the equivalent of Omni's `/api/tools`) so Core can drive Omni/Go capabilities. High-stakes
  tools (sos/dispatch/escalate) stay **confirm-gated**.
- **Proactive alert push** → Core/products → Laravel → client via the existing **Reverb**
  channels (replaces Omni's `/api/core/stream` SSE).
- **Command-center snapshot** → `GET /api/v1/core/command-center` aggregate for HQ.

Config: `SENTRIX_CORE_ENDPOINT`, `SENTRIX_CORE_API_KEY` (send `X-Service-Token`), all
env-gated and fail-safe — Core offline must never break a product request.

---

## 5. Recommended build queue

1. **Ledger** + **CRM** — finish the Omni business layer (already queued, small, high-clarity).
2. **Core bridge** (§4) — unlocks the AI agent across the whole product; relatively small.
3. **Security Command / CAD** (§3.4) — the biggest functional gap; do it as its own phase.
4. **Evidence + Retention + Intel** (§3.6–3.8) — the Omni enterprise/compliance surface.
5. **Safe Rides** (§3.5) — the consumer growth engine; large, can run in parallel.
6. **Ingest/Signals pipeline** (§3.1) — wire real detections in (depends on Core + workers).
7. **Partial closeouts** (§2) — community voting, rewards gamification, places proxies, NDPR self-service, payments PSP.

Everything above is contract-faithful to the two source repos; nothing the team built is
dropped — it is either ✅ already in Laravel, 🟡 partially there, ❌ queued as a new domain,
or 🐍 reached through Core over HTTP.
