# Public API v1 (Tier 2)

Source: `routes/api_v1.php`, mounted at `/api/v1` (`bootstrap/app.php`, `Route::middleware('api')->prefix('api/v1')->name('api.v1.')`). Controllers: `app/Http/Controllers/Api/V1/*`. Base URL for every example below: `https://vpshrms.shurttech.com`.

This surface is **key-authenticated** (an `ApiClient` record — an API integration partner or the biometric bridge — not a logged-in user) and is completely separate from the mobile app's `/api/*` surface documented elsewhere in this folder: different auth mechanism, different response envelope, different error format. It is explicitly **additive-only** — breaking changes go to a future `/api/v2`.

## Auth

Every route except `GET /api/v1/ping` requires:

```
Authorization: Bearer <key_id>.<secret>
```

Resolved by the `apikey` middleware (`ResolveApiClient`) against the `api_clients` table (`key_id` + hashed `secret`, must be `isUsable()`, i.e. active and not expired). On success the tenant is taken from the `ApiClient`'s own `tenant_id` — **never** from a header or body param — and bound as `current_tenant`/`current_api_client` for the rest of the request. Every call is logged to `ApiRequestLog` (method, path, status, duration, ip, idempotency key).

| Condition | Status | `error.code` | `error.message` |
|---|---|---|---|
| No `Authorization` header, or bearer has no `.` separator | 401 | `unauthenticated` | "Missing or malformed API key." |
| `key_id` not found / secret mismatch / key disabled or expired | 401 | `unauthenticated` | "Invalid or inactive API key." |
| Key's tenant no longer exists | 401 | `unauthenticated` | "API key is not attached to a tenant." |

## Scopes

Each functional route group additionally requires one `scope:<name>` on the `ApiClient` (`EnsureApiScope` middleware). Scopes used: `attendance:read`, `attendance:write`, `regularization:read`, `regularization:write`, `analytics:read`, `biometric:read`, `biometric:write`.

| Condition | Status | `error.code` |
|---|---|---|
| Client authenticated but lacks the required scope | 403 | `insufficient_scope` (message: `"This API key lacks the '<scope>' scope."`) |

## Response envelope (`App\Http\ApiResponse`)

- **Success** — `ApiResponse::ok($data, $meta = [], $status = 200)`:
  ```json
  { "data": <payload>, "meta": { "request_id": "<uuid>" } }
  ```
  (`meta` may carry extra keys, e.g. `from`/`to` on the trends endpoint.)
- **Paginated success** — `ApiResponse::paginated($paginator, $map)`: same shape, plus `meta.pagination`:
  ```json
  { "data": [ ... ], "meta": { "request_id": "<uuid>", "pagination": { "total": 137, "per_page": 50, "current_page": 1, "last_page": 3 } } }
  ```
- **Failure** — `ApiResponse::fail($code, $message, $details = [], $status = 422)`:
  ```json
  { "error": { "code": "...", "message": "...", "details": [] }, "meta": { "request_id": "<uuid>" } }
  ```

`request_id` echoes the inbound `X-Request-Id` header if present, otherwise a generated UUID; it is also always set as the `X-Request-Id` response header by the `apiv1` middleware.

## Shared error taxonomy (`App\Http\ApiExceptionRenderer`)

Any exception thrown by a `/api/v1/*` request that isn't caught in the controller is mapped centrally:

| Exception | `error.code` | HTTP status | Notes |
|---|---|---|---|
| `Illuminate\Validation\ValidationException` | `validation_failed` | 422 | `error.details` = Laravel's per-field error map |
| `Illuminate\Auth\AuthenticationException` | `unauthenticated` | 401 | |
| `AuthorizationException` / `AccessDeniedHttpException` | `forbidden` | 403 | |
| `ModelNotFoundException` / `NotFoundHttpException` | `not_found` | 404 | |
| `TooManyRequestsHttpException` | `rate_limited` | 429 | |
| `App\Exceptions\ApiException` (thrown explicitly in these controllers) | its own `errorCode` | its own `status` | `error.details` = its own `details` array |
| any other `HttpExceptionInterface` | `http_error` | its status code | |
| anything else (uncaught) | `server_error` | 500 | message hidden in production (`config('app.debug')` gates it) |

Below, each endpoint's "domain errors" table lists the specific `ApiException('<code>', '<message>', <status>)` calls made in that controller — these ride the `App\Exceptions\ApiException` row above.

## Idempotency

All unsafe methods (`POST`/`PUT`/`PATCH`/`DELETE`) in the authenticated group run through the `idempotency` middleware. Sending an `Idempotency-Key` header is **optional**, but recommended for every write below (attendance mark, regularization decision, biometric ingest endpoints):

| Behavior | Status | `error.code` |
|---|---|---|
| Key omitted | request just runs normally, no dedupe | — |
| Key length > 80 chars | 400 | `invalid_idempotency_key` |
| Same key + identical request body seen before, response already stored | replays the stored response, header `Idempotent-Replayed: true` | — |
| Same key + identical body, but the original request is still in flight | 409 | `idempotency_in_progress` |
| Same key + a **different** request body than before | 409 | `idempotency_key_reused` |
| First use of a key | request runs, response stored for 24h, header `Idempotent-Replayed: false` | — |

## Rate limiting

`throttle:api-public` applies to the whole authenticated group: `ApiClient.rate_limit_per_min` requests/minute (default 60) keyed per client, or per source IP if no client is bound yet. Exceeding it raises `TooManyRequestsHttpException` → 429 `rate_limited` per the table above.

## Tenant scope

Every endpoint below is scoped to `current_tenant` (the `ApiClient`'s tenant) — no cross-tenant access is possible through this surface; a `user_id`/`actor_user_id`/regularization id belonging to another tenant resolves as "not found"/"not authorized", never leaks another tenant's data.

---

## GET /api/v1/ping
**Purpose:** Unauthenticated liveness probe.
**Auth:** None (public).
**Required scope:** None.
**Tenant scope:** N/A.
**Headers:** None required.
**Request parameters / body:** None.
**File uploads:** None.

**Sample request:**
```bash
curl https://vpshrms.shurttech.com/api/v1/ping
```

**Success response — 200:**
```json
{
  "data": {
    "status": "ok",
    "service": "attendance-api",
    "version": "v1",
    "time": "2026-09-28T10:15:00+00:00"
  },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:** None specific to this route (no auth/scope layer applies).
**Special behavior / notes:** Not subject to `apikey`/`throttle:api-public`/`idempotency` — only the `apiv1` middleware (forces `Accept: application/json`, stamps `X-Request-Id`) wraps it.

---

## GET /api/v1/attendance
**Purpose:** List raw attendance rows for the tenant (optionally one user), paginated.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `attendance:read`.
**Tenant scope:** `Attendance` query is implicitly tenant-scoped via the model's `TenantTrait` global scope bound from `current_tenant`.
**Headers:** `Authorization` (required). `Idempotency-Key` not applicable (GET).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `user_id` | integer | No | Filter to one user. |
| `from` | date | No | Default: today − 30 days. |
| `to` | date | No | Default: today. |
| `per_page` | integer | No | 1–200, default 50. |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/attendance \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d user_id=42 -d from=2026-09-01 -d to=2026-09-28 -d per_page=50
```

**Success response — 200:**
```json
{
  "data": [
    {
      "id": 9001,
      "user_id": 42,
      "date": "2026-09-28",
      "clock_in": "09:02:00",
      "clock_out": "18:10:00",
      "clock_in_utc": "2026-09-28T03:32:00+00:00",
      "clock_out_utc": "2026-09-28T12:40:00+00:00",
      "timezone": "Asia/Kolkata",
      "worked_hours": 8.13,
      "attendance_status": "present",
      "effective_status": "present",
      "is_regularized": false
    }
  ],
  "meta": {
    "request_id": "b3f1...",
    "pagination": { "total": 214, "per_page": 50, "current_page": 1, "last_page": 5 }
  }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `attendance:read` |
| 422 | validation_failed | e.g. `from`/`to` not a valid date, `per_page` out of 1–200 |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Ordered by `date` then `id` ascending. `worked_hours`, `attendance_status`, `effective_status` reflect whatever policy/regularization engine already computed on the row (no recompute here).

---

## GET /api/v1/attendance/summary
**Purpose:** Monthly attendance summary for one user (present/absent/leave/overtime aggregates), delegated to `AttendanceSummaryService::getMonthly()`.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `attendance:read`.
**Tenant scope:** the target `user_id` must belong to `current_tenant` or the call 404s.
**Headers:** `Authorization` (required).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `user_id` | integer | **Yes** | |
| `month` | string (`Y-m`) | No | Default: current month. |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/attendance/summary \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d user_id=42 -d month=2026-09
```

**Success response — 200:**
```json
{
  "data": { "...": "shape defined by AttendanceSummaryService::getMonthly(), e.g. days_present, days_absent, days_on_leave, total_overtime_hours, ..." },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `attendance:read` |
| 422 | validation_failed | `user_id` missing/non-integer, `month` not `Y-m` |
| 404 | user.not_in_tenant | `user_id` doesn't belong to the caller's tenant |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** The exact field set of the summary object comes from `App\Services\AttendanceSummaryService::getMonthly()` (shared with the mobile app's own performance/summary screens) — treat it as that service's documented output, not re-specified here.

---

## POST /api/v1/attendance/mark
**Purpose:** Admin/HR/manager-driven manual attendance marking for one user (present/absent/half-day/leave/holiday/week-off) over a date or date range — the same entry point the web team-attendance screen uses (`AttendanceEntryService::markStatus()`).
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `attendance:write`.
**Tenant scope:** both `user_id` and `actor_user_id` must belong to `current_tenant`.
**Headers:** `Authorization` (required), `Content-Type: application/json`, `Idempotency-Key` (optional but recommended — this is a write).

**Request parameters / body** (JSON):
| Field | Type | Required | Notes |
|---|---|---|---|
| `user_id` | integer | **Yes** | Employee whose attendance is being marked. |
| `actor_user_id` | integer | **Yes** | The tenant user performing the action — must pass `RbacService::can($actor,'attendance','edit')`. |
| `date` | date | **Yes** | Must be `<= today`. |
| `end_date` | date | No | If given: `>= date` and `<= today` (marks a range). |
| `status` | string | **Yes** | One of: `present`, `absent`, `half_day`, `on_leave`, `first_half_leave`, `second_half_leave`, `holiday`, `weekoff` (`AttendanceStatus::markable()` — a subset of the full status enum; `late`/`early_departure`/`overtime` are computed, not hand-set). |
| `clock_in` | string (`H:i`) | No | |
| `clock_out` | string (`H:i`) | No | |
| `clock_out_next_day` | boolean | No | `true` = `clock_out` is on the day after `date` (e.g. `06:30` → `09:00` next morning = 26.5 h). Added 2026-10-09. |
| `leave_type_id` | integer | No | Relevant when `status` is a leave kind. |
| `remarks` | string | No | Max 500 chars. |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/attendance/mark \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: mark-42-2026-09-28" \
  -d '{
    "user_id": 42,
    "actor_user_id": 7,
    "date": "2026-09-28",
    "status": "present",
    "clock_in": "09:00",
    "clock_out": "18:00",
    "remarks": "Manual correction"
  }'
```

**Success response — 201:**
```json
{
  "data": {
    "marked_days": 1,
    "is_update": false,
    "attendance": {
      "id": 9001,
      "date": "2026-09-28",
      "attendance_status": "present",
      "effective_status": "present"
    },
    "leave_created": null
  },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `attendance:write` |
| 422 | validation_failed | required field missing, bad date/time format, `end_date < date`, dates in the future |
| 422 | attendance.invalid_status | `status` not in the markable list (`error.details.allowed` lists the valid values) |
| 403 | actor.not_authorized | `actor_user_id` not found in tenant, or lacks `attendance:edit` permission |
| 404 | user.not_in_tenant | `user_id` not found in tenant |
| 429 | rate_limited | over the per-client rate limit |
| 409 | idempotency_key_reused / idempotency_in_progress | idempotency key conflict |

**Special behavior / notes:** Idempotency-safe (dedupe via `Idempotency-Key`). `leave_created` is the created `leave_id` when a leave-kind status auto-creates a `leaves` row, else `null`. `marked_days` counts days affected when `end_date` spans a range.

---

## GET /api/v1/regularizations
**Purpose:** List attendance-regularization requests for the tenant, optionally filtered by status/user, paginated.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `regularization:read`.
**Tenant scope:** implicit via `AttendanceRegularization`'s tenant global scope.
**Headers:** `Authorization` (required).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | One of `pending`, `approved`, `rejected`. |
| `user_id` | integer | No | |
| `per_page` | integer | No | 1–200, default 50. |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/regularizations \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d status=pending -d per_page=25
```

**Success response — 200:**
```json
{
  "data": [
    {
      "id": 501,
      "user_id": 42,
      "date": "2026-09-27",
      "request_type": "both",
      "in_time": "09:15",
      "out_time": "18:05",
      "reason": "Forgot to clock in",
      "status": "pending",
      "approved_by": null,
      "approved_date": null
    }
  ],
  "meta": { "request_id": "b3f1...", "pagination": { "total": 4, "per_page": 25, "current_page": 1, "last_page": 1 } }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `regularization:read` |
| 422 | validation_failed | invalid `status` value, `per_page` out of range |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Ordered by `id` descending (newest first).

---

## POST /api/v1/regularizations/{id}/decision
**Purpose:** Approve or reject a pending regularization request through the same approval funnel as the web/mobile flows (`ApprovalService::decide()` if a multi-level workflow is configured, otherwise applied directly).
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `regularization:write`.
**Tenant scope:** `actor_user_id` must belong to `current_tenant`; the regularization's owner is checked via `scopeCoversOwner()` — the actor must be an admin/HR, or a manager who actually covers that employee (not just "any manager in the tenant").

**Headers:** `Authorization` (required), `Content-Type: application/json`, `Idempotency-Key` (optional but recommended).

**Request parameters / body** (JSON) + path:
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer (path) | **Yes** | Regularization id. |
| `decision` | string | **Yes** | `approved` or `rejected`. |
| `actor_user_id` | integer | **Yes** | Must be admin/HR, or a manager covering the request's owner. |
| `remarks` | string | No | Max 500 chars. |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/regularizations/501/decision \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -H "Content-Type: application/json" \
  -d '{ "decision": "approved", "actor_user_id": 7, "remarks": "Confirmed with employee" }'
```

**Success response — 200:**
```json
{
  "data": {
    "id": 501,
    "status": "approved",
    "workflow_pending": false,
    "decided_by": 7
  },
  "meta": { "request_id": "b3f1..." }
}
```
(If a multi-level approval workflow applies, `status` reflects the workflow record's status and `workflow_pending` is `true` when it isn't fully resolved yet.)

**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `regularization:write` |
| 422 | validation_failed | `decision` not `approved`/`rejected`, `actor_user_id` missing |
| 404 | regularization.not_found | `{id}` doesn't exist |
| 409 | regularization.not_pending | already decided (message includes its current status) |
| 403 | actor.not_authorized | actor not found in tenant, or doesn't cover this request's owner |
| 403 | approval.not_permitted | the configured `ApprovalService` workflow rejected this actor/decision (`RuntimeException` from the workflow) |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** When approved and no workflow intercepts it, `AttendanceEntryService::applyRegularization()` runs inside the same DB transaction as the status update, so the underlying attendance row is corrected atomically with the decision.

---

## GET /api/v1/analytics/present-now
**Purpose:** Real-time count of employees currently clocked in (open attendance row today) vs. active headcount.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `analytics:read`.
**Tenant scope:** `current_tenant` only.
**Headers:** `Authorization` (required).
**Request parameters / body:** None.
**File uploads:** None.

**Sample request:**
```bash
curl https://vpshrms.shurttech.com/api/v1/analytics/present-now \
  -H "Authorization: Bearer ak_live_123.s3cr3t"
```

**Success response — 200:**
```json
{
  "data": { "present_now": 84, "active_headcount": 120, "as_of": "2026-09-28T10:15:00+00:00" },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `analytics:read` |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Result is cached 60 seconds per tenant (`Cache::remember`), so it may lag real-time clock-ins by up to a minute.

---

## GET /api/v1/analytics/trends
**Purpose:** Daily attendance trend series (present/half-day/on-leave/absent-estimate/late-minutes/worked-hours/absence-rate) over a date range, optionally scoped to one department.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `analytics:read`.
**Tenant scope:** `current_tenant` only; `department_id` (if given) further restricts the user set via `user_job_details`.
**Headers:** `Authorization` (required).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `from` | date | No | Default: today − 30 days. |
| `to` | date | No | Default: today. |
| `department_id` | integer | No | |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/analytics/trends \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d from=2026-09-01 -d to=2026-09-28
```

**Success response — 200:**
```json
{
  "data": [
    {
      "date": "2026-09-01",
      "present": 112,
      "half_day": 3,
      "on_leave": 5,
      "absent_est": 0,
      "late_minutes": 240,
      "worked_hours": 892.5,
      "absence_rate": 0.025
    }
  ],
  "meta": { "request_id": "b3f1...", "from": "2026-09-01", "to": "2026-09-28" }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `analytics:read` |
| 422 | validation_failed | invalid `from`/`to` date, non-integer `department_id` |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Cached 10 minutes per `(tenant, from, to, department)` key. `absent_est` is a headcount-minus-present/half/leave estimate, not a stored absence record. `worked_hours` figures are pre-rounded to 2 decimals.

---

## GET /api/v1/analytics/overtime-cost
**Purpose:** Estimated overtime cost for a given month, derived from each active user's monthly overtime hours × an average hourly rate × the tenant's overtime multiplier.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `analytics:read`.
**Tenant scope:** `current_tenant` only.
**Headers:** `Authorization` (required).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `month` | string (`Y-m`) | No | Default: current month. |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/analytics/overtime-cost \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d month=2026-09
```

**Success response — 200:**
```json
{
  "data": {
    "year_month": "2026-09",
    "overtime_hours": 340.5,
    "avg_hourly_rate": 215.0,
    "multiplier": 1.5,
    "estimated_cost": 109811.25
  },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `analytics:read` |
| 422 | validation_failed | `month` not in `Y-m` format |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** This is explicitly an **estimate**, not an authoritative payroll figure (per an in-code comment). When the tenant is on the dynamic payroll engine (`payroll_dynamic_ui_enabled`), the average hourly rate is approximated from average CTC over a 26-day/8-hour month, since no dynamic structure stores an explicit hourly rate; on the legacy engine it reads `payroll_masters.hourly_rate_applied` directly. `multiplier` comes from the tenant's resolved attendance policy for that month (`PolicyResolver`).

---

## GET /api/v1/analytics/anomalies
**Purpose:** List detected attendance anomalies (e.g. missed punches, unusual patterns) for the tenant, paginated.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `analytics:read`.
**Tenant scope:** implicit via `AttendanceAnomaly`'s tenant global scope.
**Headers:** `Authorization` (required).
**Request parameters / body** (query string):
| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | One of `open`, `ack`, `dismissed`. |
| `type` | string | No | Anomaly type key (as produced by `AnomalyScanner`). |
| `per_page` | integer | No | 1–200, default 50. |

**File uploads:** None.

**Sample request:**
```bash
curl -G https://vpshrms.shurttech.com/api/v1/analytics/anomalies \
  -H "Authorization: Bearer ak_live_123.s3cr3t" \
  -d status=open
```

**Success response — 200:**
```json
{
  "data": [
    {
      "id": 77,
      "user_id": 42,
      "date": "2026-09-27",
      "type": "missing_clock_out",
      "severity": "medium",
      "status": "open",
      "detail": "No clock-out recorded after 14h since clock-in.",
      "created_at": "2026-09-28T02:00:00+00:00"
    }
  ],
  "meta": { "request_id": "b3f1...", "pagination": { "total": 6, "per_page": 50, "current_page": 1, "last_page": 1 } }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `analytics:read` |
| 422 | validation_failed | invalid `status`, non-string `type`, `per_page` out of range |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Ordered by `id` descending. Anomalies are produced by `AnomalyScanner` (a separate scheduled/batch process) — this endpoint is read-only.

---

## Biometric-terminal bridge

The following five endpoints are the ingest/control surface the on-prem **SBXPC Windows bridge service** (`hrm-biometric-bridge`) uses to post punches and sync each device's user roster. They are consumed by that bridge, not by end-user/mobile clients.

### POST /api/v1/biometric/punches
**Purpose:** Batch-ingest raw punch events from one biometric device. Idempotent per punch via a dedupe key (not via the `Idempotency-Key` header, though that's also honored).
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:write`.
**Tenant scope:** resolved from the device (`BiometricDevice.tenant_id`), which itself must belong to `current_tenant`.
**Headers:** `Authorization` (required), `Content-Type: application/json`, `Idempotency-Key` (optional, recommended for whole-batch replay safety).

**Request parameters / body** (JSON):
| Field | Type | Required | Notes |
|---|---|---|---|
| `device_serial` | string | **Yes** | Must match an active `BiometricDevice` in the tenant. |
| `batch_ref` | string | No | Max 120 chars, bridge-side batch identifier (not persisted per-punch here). |
| `punches` | array | **Yes** | 1..`biometric.ingest_batch_max` items (config default 500). |
| `punches.*.enroll_no` | string | **Yes** | Max 64 chars — device-side user id. |
| `punches.*.punched_at` | date | **Yes** | |
| `punches.*.raw_verify_mode` | integer | No | |
| `punches.*.method` | string | No | Max 20 chars. |
| `punches.*.direction` | string | No | `in` or `out`. |
| `punches.*.temperature` | numeric | No | |
| `punches.*.device_pos` | integer | No | |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/biometric/punches \
  -H "Authorization: Bearer ak_bio_1.s3cr3t" \
  -H "Content-Type: application/json" \
  -d '{
    "device_serial": "SBX-0042",
    "batch_ref": "2026-09-28T10:00:00Z",
    "punches": [
      { "enroll_no": "1007", "punched_at": "2026-09-28 09:01:12", "direction": "in", "method": "fingerprint" }
    ]
  }'
```

**Success response — 200:**
```json
{
  "data": { "accepted": 1, "duplicates": 0, "skipped_unmapped": 0, "unknown_enrolls": [] },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:write` |
| 422 | validation_failed | missing/invalid fields, batch exceeds max size |
| 404 | device.unknown | `device_serial` not found in tenant |
| 409 | device.inactive | device exists but `is_active = false` |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** A punch already seen (same serial + enroll_no + punched_at + raw_verify_mode) is counted as a duplicate and skipped. An `enroll_no` not yet mapped to a user is auto-resolved when it's a numeric id of an active tenant employee (`resolveEnroll()`, gated by `config('biometric.roster.auto_resolve_punch_enrolls')`, default on); otherwise it's stored with `status = skipped` and listed under `unknown_enrolls`. Every accepted punch dispatches `ProcessBiometricPunch` as a queued job (async attendance processing, not synchronous). Updates the device's `last_seen_at`/`last_punch_at`.

---

### POST /api/v1/biometric/devices/{serial}/heartbeat
**Purpose:** Liveness ping from the bridge for one device.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:write`.
**Tenant scope:** device must belong to `current_tenant`.
**Headers:** `Authorization` (required), `Content-Type: application/json`.
**Request parameters / body** (JSON):
| Field | Type | Required | Notes |
|---|---|---|---|
| `serial` | string (path) | **Yes** | |
| `model` | string | No | Updates `BiometricDevice.model` if provided. |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/biometric/devices/SBX-0042/heartbeat \
  -H "Authorization: Bearer ak_bio_1.s3cr3t" -H "Content-Type: application/json" \
  -d '{ "model": "SBXPC-500" }'
```

**Success response — 200:**
```json
{ "data": { "ok": true }, "meta": { "request_id": "b3f1..." } }
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:write` |
| 404 | device.unknown | serial not found in tenant |
| 409 | device.inactive | device disabled |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Updates `last_seen_at` to now.

---

### POST /api/v1/biometric/devices/{serial}/enrollments
**Purpose:** Bridge reports the device's on-device user list so unmapped device users can be linked to (or auto-provisioned as) tenant employees.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:write`.
**Tenant scope:** device must belong to `current_tenant`; any auto-provisioned user is created in that tenant.
**Headers:** `Authorization` (required), `Content-Type: application/json`, `Idempotency-Key` (optional, recommended).
**Request parameters / body** (JSON):
| Field | Type | Required | Notes |
|---|---|---|---|
| `serial` | string (path) | **Yes** | |
| `enrollments` | array | **Yes** | |
| `enrollments.*.enroll_no` | string | **Yes** | Max 64 chars. |
| `enrollments.*.name` | string | No | Max 120 chars, name as stored on the device. |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/biometric/devices/SBX-0042/enrollments \
  -H "Authorization: Bearer ak_bio_1.s3cr3t" -H "Content-Type: application/json" \
  -d '{ "enrollments": [ { "enroll_no": "1007", "name": "Asha Rao" } ] }'
```

**Success response — 200:**
```json
{ "data": { "added": 1, "linked": 0, "total": 24 }, "meta": { "request_id": "b3f1..." } }
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:write` |
| 422 | validation_failed | missing `enrollments`/`enroll_no` |
| 404 | device.unknown | serial not found in tenant |
| 409 | device.inactive | device disabled |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** If `enroll_no` is numeric and matches an existing active employee's id, the row self-links (`linked++`) — no manual mapping needed. If it's unrecognized and the device has `allow_direct_onboarding` enabled, a brand-new employee is auto-provisioned via `BiometricEmployeeProvisioningService` (`added++`); if onboarding is blocked (`DirectOnboardingBlockedException`), the row is saved with `last_error` set and no user is created. Otherwise the enrollment is stored unlinked pending manual admin mapping.

---

### GET /api/v1/biometric/devices/{serial}/enrollments
**Purpose:** Return the enroll_no → user mapping for one device (for the bridge or an admin tool to inspect).
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:read`.
**Tenant scope:** device must belong to `current_tenant`.
**Headers:** `Authorization` (required).
**Request parameters / body:** `serial` (path, required). No query params.
**File uploads:** None.

**Sample request:**
```bash
curl https://vpshrms.shurttech.com/api/v1/biometric/devices/SBX-0042/enrollments \
  -H "Authorization: Bearer ak_bio_1.s3cr3t"
```

**Success response — 200:**
```json
{
  "data": [
    { "enroll_no": "1007", "user_id": 42, "employee_id": "EMP-1007", "name_on_device": "Asha Rao" }
  ],
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:read` |
| 404 | device.unknown | serial not found in tenant |
| 409 | device.inactive | device disabled |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Not paginated — returns the full enrollment list for the device. `employee_id`/`user_id` are `null` for rows not yet linked to a tenant user.

---

### GET /api/v1/biometric/devices/{serial}/roster
**Purpose:** Desired-state feed for the bridge's provisioner — which device-user records to upsert or delete on the physical terminal.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:read`.
**Tenant scope:** device must belong to `current_tenant`.
**Headers:** `Authorization` (required).
**Request parameters / body:** `serial` (path, required). No query params.
**File uploads:** None.

**Sample request:**
```bash
curl https://vpshrms.shurttech.com/api/v1/biometric/devices/SBX-0042/roster \
  -H "Authorization: Bearer ak_bio_1.s3cr3t"
```

**Success response — 200:**
```json
{
  "data": {
    "upserts": [
      { "id": 1007, "name": "Asha Rao", "privilege": 0, "card": null }
    ],
    "deletes": [1013]
  },
  "meta": { "request_id": "b3f1..." }
}
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:read` |
| 404 | device.unknown | serial not found in tenant |
| 409 | device.inactive | device disabled |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** Only returns `biometric_enrollments` rows in `sync_state` `pending` (→ `upserts`) or `removing` (→ `deletes`) — already-synced rows are omitted. `privilege` comes from the device's `default_privilege`. The bridge is expected to apply these and report back via `roster/ack`.

---

### POST /api/v1/biometric/devices/{serial}/roster/ack
**Purpose:** Bridge reports back which roster upserts/deletes it actually applied on the physical device, closing the provisioning loop.
**Auth:** `Authorization: Bearer <key_id>.<secret>`.
**Required scope:** `biometric:write`.
**Tenant scope:** device must belong to `current_tenant`.
**Headers:** `Authorization` (required), `Content-Type: application/json`, `Idempotency-Key` (optional, recommended).
**Request parameters / body** (JSON):
| Field | Type | Required | Notes |
|---|---|---|---|
| `serial` | string (path) | **Yes** | |
| `applied` | array | **Yes** | |
| `applied.*.id` | integer | **Yes** | The `device_user_id` acted on. |
| `applied.*.action` | string | **Yes** | `upsert` or `delete`. |
| `applied.*.ok` | boolean | **Yes** | Whether the device accepted the write. |
| `applied.*.error` | string | No | Max 300 chars, device-reported error when `ok` is false. |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/v1/biometric/devices/SBX-0042/roster/ack \
  -H "Authorization: Bearer ak_bio_1.s3cr3t" -H "Content-Type: application/json" \
  -d '{ "applied": [ { "id": 1007, "action": "upsert", "ok": true } ] }'
```

**Success response — 200:**
```json
{ "data": { "synced": 1, "removed": 0, "failed": 0 }, "meta": { "request_id": "b3f1..." } }
```
**Error responses:**
| Status | code | Condition |
|---|---|---|
| 401 | unauthenticated | bad/missing key |
| 403 | insufficient_scope | key lacks `biometric:write` |
| 422 | validation_failed | missing/invalid `applied.*` fields |
| 404 | device.unknown | serial not found in tenant |
| 409 | device.inactive | device disabled |
| 429 | rate_limited | over the per-client rate limit |

**Special behavior / notes:** `ok: false` marks the enrollment `sync_state = failed` with `last_error` set (still counted, no HTTP error). `action: delete` + `ok: true` hard-deletes the `biometric_enrollments` row. `action: upsert` + `ok: true` marks it `synced` and stamps `synced_at`/`name_pushed`/`card_pushed`. Unknown `id`s (no matching enrollment row for that device) are silently skipped. Also updates the device's `last_seen_at`.
