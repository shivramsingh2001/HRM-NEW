# HRM API Documentation — Conventions & Index

Source of truth for this documentation set: the actual code in `routes/api.php`, `routes/api_v1.php`, their controllers, services, and middleware, as of 2026-09-28. Nothing here is invented — every endpoint, field, and response shape is read directly from the implementation. If a detail isn't shown in the code, it's called out as "not explicitly documented" rather than guessed.

**Base URL:** `https://vpshrms.shurttech.com`

## Two API surfaces (do not confuse them)

This project exposes **two separate, differently-authenticated JSON APIs**, plus a server-rendered Blade admin panel (`routes/web.php`) that is out of scope for this document (it's an internal AJAX surface for the admin UI, not a public/mobile API — see `hrm (3)/CLAUDE.md`).

| | Legacy / Mobile API | Public API v1 (Tier 2) |
|---|---|---|
| Route file | `routes/api.php` | `routes/api_v1.php` |
| Mount | `https://vpshrms.shurttech.com/api/...` | `https://vpshrms.shurttech.com/api/v1/...` |
| Consumer | Flutter mobile app | Third-party / server-to-server integrations, the SBXPC biometric bridge |
| Auth | JWT bearer token (`php-open-source-saver/jwt-auth`), one human user per token | API key pair (`ApiClient` record), no human user |
| Response envelope | **None** — every action defines its own ad hoc JSON shape | Fixed envelope (`data`/`meta` on success, `error`/`meta` on failure) |
| Status codes | Historically `200` even for business failures (deliberate, frozen behavior — see below) | Real HTTP status codes throughout |
| Stability | **Frozen** — shapes must not change (`ApiV1EnvelopeTest` guards this from the other side) | Additive-only; breaking changes go to a future `/api/v2` |
| Documented in | `docs/api/01-*.md` through `docs/api/08-*.md` | `docs/api/09-public-api-v1.md` |

Module files in this set:

1. [`01-auth-and-account.md`](01-auth-and-account.md) — login, password reset, OTP, logout, account deletion, FCM token, face registration, change password
2. [`02-employee-self-service.md`](02-employee-self-service.md) — holidays, user profile, country/state/city lookups, team view, leave, announcements
3. [`03-expense-and-loan.md`](03-expense-and-loan.md) — expense claims/payments, loans
4. [`04-attendance-and-requests.md`](04-attendance-and-requests.md) — clock in/out, GPS tracking, regularization, WFH/travel requests, overtime
5. [`05-performance-shift-payroll.md`](05-performance-shift-payroll.md) — performance summaries/reviews, shift plan, payslips
6. [`06-project-and-task.md`](06-project-and-task.md) — projects, task assignment/approval
7. [`07-notifications-meetings-offboarding.md`](07-notifications-meetings-offboarding.md) — notifications, meetings/MOM, offboarding
8. [`08-ai-assistant-module.md`](08-ai-assistant-module.md) — read-only per-domain data feed for the AI assistant (`/api/ai/*`)
9. [`09-public-api-v1.md`](09-public-api-v1.md) — Tier 2 public API: attendance, regularizations, analytics, biometric-terminal bridge

## Legacy / Mobile API (`/api/*`) — shared conventions

### Authentication

- **Pre-auth routes** (`/api/login`, `/api/forgot-password`, `/api/reset-password`, `/api/send-otp`, `/api/login-otp`): no bearer token yet. Because these routes look users up by `employee_id`/`email`/`contact` — none of which are unique across tenants — the caller **must** identify its tenant explicitly via one of, in priority order: `X-Tenant` header, `X-Tenant-ID` header, or a `tenant_id` body field, matched against `tenants.subdomain` or `tenants.id`.
  - Missing identifier → `400` `{"success": false, "message": "Missing tenant identifier. Include an X-Tenant header with your request."}`
  - No matching active tenant → `400` `{"success": false, "message": "Company not found or inactive."}`
- **All other routes**: require
  - `Authorization: Bearer <jwt>` — a JWT issued by `/api/login` or `/api/login-otp` (`auth('api')->login($user)`).
  - `Device-Token: <token>` — must match the stored `users.last_login_token` for that user (`singleLogin` / `CheckSingleDeviceLogin` middleware — single active mobile session per user). Mismatch → `401` `{"status": false, "message": "Your account is logged in on another device. Please login again."}`.
  - Missing/invalid/expired/blacklisted JWT → `401` `{"message": "Unauthenticated."}` (mapped centrally in `bootstrap/app.php` from both `AuthenticationException` and JWT's own `JWTException`, deliberately kept identical to the pre-JWT Sanctum shape).
  - Tenant is then resolved **only** from the authenticated user's own `tenant_id` — no client-supplied header is honored once a JWT is present.

### Response shape

No fixed envelope. Each controller action hand-rolls its own `response()->json([...], $code)`. The dominant pattern seen across the mobile API is `{"success": bool, "message": string, "data": ...}` or `{"status": bool, "message": string, ...}`, but this **varies by controller and even by action** — always check the specific endpoint's documented "Success response" / "Error responses" rather than assuming a shape.

A widely-observed but non-universal convention (see `AuthController` specifically): validation/business failures return HTTP `200` with `{"success": false, ...}` rather than `4xx` — this is deliberate for the mobile client and intentionally not "fixed" elsewhere in the codebase. Endpoints that genuinely differ from this are called out per-endpoint in the module files.

Mutating actions are almost universally wrapped in `try { ... } catch (\Exception $e) { return response()->json(['success' => false, 'message' => '...'], 500); }` — a real server-side exception typically surfaces as `500` with a generic message, not the specific exception text.

### Rate limits (named limiters, `App\Providers\AppServiceProvider`)

| Limiter | Applied to | Limit | 429 response body |
|---|---|---|---|
| `login` | `POST /api/login` | 5/min per `employee_id\|email` + IP, and 100/day per IP | `{"success": false, "message": "Too many attempts. Please wait a moment and try again."}` |
| `otp-request` | `POST /api/send-otp` | 3/min and 10/day per `mobile_no` | same shape as `login` |
| `otp-verify` | `POST /api/login-otp` | 5/min per `mobile_no` | same shape as `login` |
| `location-ingest` | `POST /api/user/attendance/track`, `POST /api/user/attendance/track-batch` | `config('location.throttle_per_minute', 40)`/min per authenticated user (or IP if unauthenticated) | `{"status": false, "message": "Too many location updates. Slow down.", "tracking_enabled": true, "next_ping_seconds": <config('location.default_ping_seconds', 60)>}` |

### Authorization (`permission:` middleware)

Some routes are additionally gated by tenant RBAC: `permission:{module},{action}` (`EnsurePermission` middleware, backed by `RbacService`). On a JSON request, a denied check returns `403` with Laravel's default abort JSON body: `{"message": "You do not have permission for this action."}`. Which routes carry this gate is called out per-endpoint in the module files (e.g. `leave,view`/`leave,approve`, `requests,view`/`requests,approve`, `meetings,view`/`meetings,create`/`meetings,edit`).

### Common HTTP status codes across the legacy API

| Status | Typical meaning |
|---|---|
| 200 | Success — **or** a business/validation failure surfaced as `{"success": false, ...}` (endpoint-dependent, see above) |
| 400 | Pre-auth tenant resolution failure |
| 401 | Missing/invalid JWT, or Device-Token mismatch |
| 403 | `permission:` middleware denial |
| 404 | Route/resource not found (Laravel default) |
| 422 | Where an endpoint does use `$request->validate()` directly (throws `ValidationException`, default Laravel JSON shape `{"message": "...", "errors": {field: [...]}}`) rather than a manual `Validator::make()` + custom response — verified per-endpoint |
| 429 | Rate limit exceeded (see table above) |
| 500 | Unhandled exception in a `try/catch` block |

## Public API v1 (`/api/v1/*`) — shared conventions

Full detail, including the exception→error-code mapping table and idempotency mechanics, is in [`09-public-api-v1.md`](09-public-api-v1.md). Summary:

- **Auth**: `Authorization: Bearer <key_id>.<secret>` against an `ApiClient` record (not a user). No tenant header — the tenant is whichever tenant the API key belongs to.
- **Scopes**: most route groups additionally require `scope:<name>` (e.g. `attendance:read`, `biometric:write`) against the authenticated `ApiClient`.
- **Envelope**: success `{"data": ..., "meta": {"request_id": "..."}}`; failure `{"error": {"code", "message", "details"}, "meta": {"request_id": "..."}}`.
- **Idempotency**: optional `Idempotency-Key` header on non-`GET` requests, 24h replay window.
- **Rate limit**: per-`ApiClient` (`rate_limit_per_min`, default 60/min), else per-IP.
- **`X-Request-Id`**: echoed on every response for support correlation.

## Multi-tenancy

Single database; almost every table carries `tenant_id`. For the legacy API, tenant is resolved from the JWT user (see above). For the Tier 2 API, tenant is resolved from the `ApiClient`'s own `tenant_id`. Models using `App\Traits\TenantTrait` apply a global query scope filtering by the currently-bound tenant automatically — this is why most endpoints below don't need an explicit `tenant_id` request field even though the underlying tables are multi-tenant.

## Pagination & filtering

Not standardized across the legacy API — each list endpoint is documented individually for whatever query parameters (if any) it actually reads and whether its response is paginated. The Tier 2 API uses a consistent `meta.pagination` block (`total`, `per_page`, `current_page`, `last_page`) wherever `ApiResponse::paginated()` is used.
