# Sentrix — Database Scalability Hardening

What was implemented to keep the database healthy as load grows from ~100 to
10,000+ users, and how to operate it. Three high-leverage changes shipped, in
order of leverage.

> Baseline (already in place before this work): ~140 indexes across 60 domain
> migrations, with composite `(organization_id, status)` / `(organization_id,
> <timestamp> DESC)` indexes matching the query shapes; the GPS-fix firehose
> (`trip_locations`) is **RANGE-partitioned by month**; PostGIS GiST indexes for
> geo; Redis for cache/queue/session; Horizon for async; eager-loading discipline.

---

## 1. Connection scaling — read/write split + timeouts + PgBouncer-ready

**Read/write split (`config/database.php`).** The `pgsql` connection now has
`read` / `write` host arrays and `sticky => true`.

- Reads go to `DB_READ_HOST` (comma-separated for multiple replicas) when set;
  otherwise both point at `DB_HOST` — **zero behavioural change on a single
  instance**, and you get replica offloading the moment `DB_READ_HOST` is set.
- `sticky` keeps reads on the writer for the rest of a request after a write, so
  an operator never reads data they just wrote (no replica-lag surprises).

**Statement timeouts (migration `…_set_postgres_session_timeouts`).** Sets, at the
role level (`ALTER ROLE CURRENT_USER SET …`, no superuser needed, pooling-safe):

- `statement_timeout` (default **30s**, `DB_STATEMENT_TIMEOUT`) — a runaway query
  is aborted instead of pinning a connection.
- `idle_in_transaction_session_timeout` (default **60s**, `DB_IDLE_TX_TIMEOUT`) —
  releases connections/locks held by a stuck client.

Generous on purpose: normal requests and jobs are never affected. Big maintenance
operations (large `CREATE INDEX`, backfills) should run `SET statement_timeout =
0` for their own session — the trigram-index migration already does.

**PgBouncer (`compose.pgbouncer.yml`, optional).** Transaction-pooling in front of
Postgres so horizontal autoscaling doesn't exhaust `max_connections`. The app is
PgBouncer-ready: set `DB_EMULATE_PREPARES=true` (config already honours it) so PDO
doesn't depend on server-side named prepared statements under transaction
pooling. Enable + env steps are documented at the top of `compose.pgbouncer.yml`.

### New env knobs
```
DB_READ_HOST=            # replica host(s), comma-separated; defaults to DB_HOST
DB_STATEMENT_TIMEOUT=30s
DB_IDLE_TX_TIMEOUT=60s
DB_EMULATE_PREPARES=false   # set true when using PgBouncer transaction pooling
```

---

## 2. Index-backed search (pg_trgm)

Evidence vault search used leading-wildcard matching (`label ILIKE '%term%'`, and
a normalised `replace(replace(plate,'-',''),' ','') LIKE '%NEEDLE%'`), which a
b-tree can't serve → sequential scans that get slow as observation volume climbs.

Migration `…_add_trigram_search_indexes_to_observations` adds (PostgreSQL):

- `CREATE EXTENSION pg_trgm`
- GIN trigram index on `label` (`observations_label_trgm`)
- GIN trigram **functional** index on the exact normalised-plate expression
  (`observations_plate_norm_trgm`) so the planner matches the query.

No query changes were needed — the controller already uses those exact
expressions; the indexes just make them index lookups. **Keep the index
expression and `EvidenceController::applyFacets` plate expression identical** if
either changes.

> Large existing tables: build these with `CREATE INDEX CONCURRENTLY` manually to
> avoid a write-lock during the build (the migration uses a plain `CREATE INDEX`,
> which is fine for a fresh/small table).

---

## 3. SQL-side aggregation + cached stats

**Intel aggregation moved into SQL (`IntelReportService`).** Previously it loaded
every incident/emergency/observation in the range into PHP and grouped in memory
— a latency/memory bomb on a busy tenant. Now every roll-up is a SQL aggregate:

- counts via `GROUP BY` (`grouped()` / `total()`)
- trends via `date_trunc(...) + to_char(...)` bucketing
- response times via `percentile_cont(0.5)` + `avg` over `EXTRACT(EPOCH …)`
- heatmap via a `round()`-grid `GROUP BY` across observations + emergencies

The JSON response shape is **unchanged** (verified by `IntelReportingTest`).

**Cached stats/overview endpoints (Redis, 30s TTL).** The dashboard polls these;
they now memoise the computed roll-up so repeated polls don't re-run the
aggregates:

| Endpoint | Cache key |
|---|---|
| Intel report | `intel:report:{org}:{range}` |
| Intel analytics | `intel:analytics:{org}:{range}:{bucket}` |
| Evidence stats | `evidence:stats:{org}` |
| Ledger stats | `ledger:stats` |
| Rides-Ops overview | `rides-ops:overview` |
| Command overview | `command:overview` |

Trade-off: a write is reflected on the next refresh after the ≤30s window. Tune
the TTL, or add explicit `Cache::forget(...)` in the relevant write paths if you
need instant stats.

---

## Apply & verify

```bash
# from sentrix-backend
./vendor/bin/sail artisan migrate          # adds timeouts + trigram indexes
./vendor/bin/sail artisan config:clear
./vendor/bin/sail test --filter='IntelReporting|EvidenceVault'   # shape/behaviour intact

# confirm the indexes exist
./vendor/bin/sail psql -c "\di+ observations_*trgm"
# confirm the planner uses them (look for 'Bitmap Index Scan ... _trgm')
./vendor/bin/sail psql -c "EXPLAIN SELECT * FROM observations WHERE label ILIKE '%red%';"
```

## Still open (next, by volume)

- **Cursor pagination** (`cursorPaginate`) on the highest-volume lists
  (observations, audit, tracking, notifications) — offset+`COUNT(*)` degrades on
  deep pages / huge tables.
- **Partition the other time-series tables** (observations, detection_events,
  audit_logs, notifications) by month, like `trip_locations`, once they get large.
- **Trigram/FTS on other search columns** (e.g. CRM, audit) if those gain
  substring search.
- **FK index audit** — `foreignUuid()` creates the constraint but not an index;
  most are covered by composite indexes, but verify single-column FK lookups.
