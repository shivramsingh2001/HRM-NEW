# Architecture

Reference for how this codebase is actually built — not idealized Laravel conventions. Read this before touching controllers, services, middleware, or routes. See also [`modules.md`](modules.md), [`database.md`](database.md), [`ui-guidelines.md`](ui-guidelines.md).

## Stack

- **Laravel 11** (`laravel/framework: ^11.31`), **PHP ^8.2**. Project root: `D:\HRMNEW\hrm (3)` (note the space + parens in the folder name — always quote the path in shell commands). Served at `https://hrm.shurttech.com` (tenant subdomains: `*.shurttech.com`).
- **Auth**: `php-open-source-saver/jwt-auth: ^2.8` only. **Sanctum is NOT installed** (not in `composer.json`) despite older notes — it was fully replaced by JWT. `config/auth.php`: `web` guard = `session` driver, `api` guard = `jwt` driver (`config/auth.php:44-47`). All mobile/API auth is JWT bearer tokens (`auth('api')->login($user)`, `auth('api')->logout()`).
- Other key packages: `barryvdh/laravel-dompdf ^3.1` (PDF payslips/reports), `kreait/firebase-php ^7.24` (FCM push), `laravel/tinker`.
- **Frontend build**: Vite (`vite.config.js`) + Tailwind (`tailwind.config.js`, `postcss.config.js`) for asset pipelines, but the actual admin UI is **Bootstrap 5**-based Blade (see [`ui-guidelines.md`](ui-guidelines.md) — `Paginator::useBootstrapFive()` is set in `AppServiceProvider::boot()`). `package.json` has minimal JS deps; most interactivity is jQuery/vanilla JS + Bootstrap JS in Blade views.
- Bootstrap file is Laravel 11-style `bootstrap/app.php` (no `app/Http/Kernel.php` — middleware, routing, and exception handling are all configured there).

## Folder structure (`app/`)

| Folder | Contents |
|---|---|
| `Http/Controllers/` | 97 controllers, organized **by module** into subfolders (`Department/`, `Attendance/`, `Payroll/`, `Recruitment/`, etc.) — see below. `Api/` subfolder holds the legacy/mobile JSON API (mirrors module folders again, e.g. `Api/Attendance/`, `Api/Auth/`), `Api/V1/` holds the newer public-API (Tier 2) controllers. |
| `Http/Middleware/` | 13 files, see [Middleware](#middleware) below. |
| `Http/Requests/` | Only 4 FormRequest classes exist — validation is overwhelmingly done inline in controllers via `Validator::make()` or `$request->validate()`, not extracted FormRequests. |
| `Http/ApiResponse.php`, `Http/ApiExceptionRenderer.php` | Shared response/error envelope helpers for the `/api/v1/*` (Tier 2) surface only — see [API response conventions](#api-response-conventions). |
| `Models/` | 114 Eloquent models, flat namespace `App\Models`. |
| `Services/` | 53 files. Mostly flat, plus subfolders for larger domains: `Services/Analytics/`, `Services/Approvals/`, `Services/Attendance/`, `Services/Biometric/`, `Services/FieldTracking/`, `Services/Payroll/`, `Services/Performance/`. Naming pattern: `{Domain}Service.php`, `{Domain}NotificationService.php`. |
| `Repositories/` + `Repositories/Interfaces/` | **Minimally used** — only `AuthRepository`/`AuthRepositoryInterface` and `UserRepository`/`UserRepositoryInterface` exist (4 files total, ~35 lines each). Bound in `app/Providers/RepositoryServiceProvider.php`. Every other model/entity is queried directly via Eloquent from controllers/services — do not assume a repository layer exists for anything but auth/user lookups. |
| `Policies/` | Only `MonthlyPayrollPolicy.php` exists. Most authorization is done via `role:` middleware or ad-hoc checks in controllers, not Policies. |
| `Observers/` | `BiometricRosterObserver` (auto-provisions employees onto biometric terminals on `User` lifecycle events), `UserRoleObserver` (keeps `users.role_id` in sync with the legacy varchar `role` column — RBAC migration in progress), `TaskProgressObserver` (keeps `projects.progress_percentage` in sync as tasks change). Registered in `AppServiceProvider::boot()`. |
| `Events/`, `Listeners/` | Domain events (attendance, leave, task, etc.) — check `EventServiceProvider`/`AppServiceProvider` for registrations if adding new ones. |
| `Jobs/` | Queued jobs (notifications, exports, etc.) — dispatched via `->dispatch()` in services/controllers, use the `jobs`/`failed_jobs` tables (`database/queue.php` driver). |
| `Notifications/`, `Mail/` | Laravel notification classes (DB + FCM channel via `app/Channels/`) and Mailables (e.g. `PasswordResetMail`). |
| `Enums/` | Only `AttendanceStatus.php` currently — most "enum-like" values (roles, statuses) are plain strings/ints validated with `in:` rules, not PHP enums. |
| `Traits/` | `TenantTrait` (multi-tenancy, see below), `AuthorizesByScope`, `BlocksLegacyPayrollWrites`, `LogsPayrollActivity`, `NotifiesExpenseEvents`, `ResolvesCurrentTenant` (Payroll Audit Phase 4/M10 — single `currentTenantId(?User $user = null): ?int` shared by `MonthlyPayrollController` (web) and `Api\Payroll\PayrollController` (mobile JWT API); prefers the container-bound `current_tenant`, falls back to the authenticated user's own `tenant_id`, then `session('tenant_id')` — reach for this instead of writing another inline tenant-resolution fallback). |
| `Support/`, `Helpers/`, `Channels/` | Misc utility classes and a custom notification channel (FCM). |
| `Console/Commands/` | Artisan commands — check here before writing a new scheduled/manual command, some domains already have maintenance commands (e.g. attendance summary rebuild). |

## Multi-tenancy pattern

Single database, `tenant_id` column on almost every table. Two pieces:

1. **`app/Http/Middleware/TenantMiddleware.php`** — resolves the current tenant and binds it as `app()->instance('current_tenant', $tenant)`, also sets `session(['tenant_id' => ..., 'tenant' => ...])` and `view()->share('currentTenant', $tenant)`. Behavior differs by request type:
   - **Web + real subdomain**: tenant resolved by subdomain against `shurttech.com` (`handleWebRequest()`); reserved subdomains (`crm`, `api`, `www`, `mail`, `admin`) and the bare main domain get no tenant. Unknown subdomain → `404`.
   - **Web + localhost/`.test`** (`isLocalHost()` / `handleLocalRequest()`): tenant resolved from, in order, the authed user → `?tenant=` / `X-Tenant` header / session / `config('tenancy.local_tenant_id')` → first active tenant. Never 404s locally.
   - **API** (`handleApiRequest()`): tenant is taken **only** from the authenticated JWT user's `tenant_id` (never a client-supplied header, since JWT is stateless the guard can resolve the user even though this middleware runs before `auth:api`). Exception: a small allowlist of **pre-auth** routes (`api/login`, `api/forgot-password`, `api/reset-password`, `api/send-otp`, `api/login-otp` — `TENANT_SELECTABLE_ROUTES` constant) may name their tenant via `X-Tenant`/`X-Tenant-ID` header or `tenant_id` body param, and **must** — this is fail-closed by design (see the long comment at `TenantMiddleware.php:132-142`: a request with no resolvable tenant used to fall through completely unscoped).
   - Registered as the `tenant` middleware alias (`bootstrap/app.php`).

2. **`app/Traits/TenantTrait.php`** — added to tenant-scoped models. `bootTenantTrait()` adds a global scope `tenant` that filters `WHERE {table}.tenant_id = current_tenant->id` whenever `app()->bound('current_tenant')`, and auto-fills `tenant_id` on `creating()`. `scopeAllTenants()` lets a query opt out (`Model::allTenants()->...`) — used sparingly, e.g. super-admin/cross-tenant contexts. Also defines a `tenant()` belongsTo relation.

The **Tier 2 public API** (`/api/v1/*`, `ResolveApiClient` middleware) resolves tenant differently again: from an `ApiClient` record matched against the `Authorization: Bearer <key_id>.<secret>` header, not from a user at all (`app()->instance('current_tenant', $tenant)` + `app()->instance('current_api_client', $client)`).

## Controller patterns

Controllers are **fat** — most business logic, validation, and persistence lives directly in the controller method, not delegated to services/models, though newer/sensitive modules (auth, payroll, RBAC, API v1) do use a service layer. Two representative examples:

- **`app/Http/Controllers/Department/DepartmentController.php`** — typical CRUD web controller. `index()` builds a raw query with `DB::raw`/`leftJoin`/`groupBy` directly in the controller and returns a Blade view (`view('client.department.department', $data)`); `store()`/`update()` validate inline with `$request->validate([...])` (including a scoped-unique rule via `Rule::unique(...)->where(fn ($q) => $q->where('tenant_id', ...))`), touch the Eloquent model directly, and return `response()->json(['success' => bool, 'message' => string], $code)` — **not** Blade redirects, even though the page itself is server-rendered (forms submit via AJAX). Every mutating action is wrapped in `try { } catch (Exception $e) { return response()->json(['success' => false, ...], 500); }`.
- **`app/Http/Controllers/Api/Auth/AuthController.php`** — legacy/mobile JSON API controller. Constructor-injects services (`AuthService`, `LoginAttemptService`, `AuthAuditService`) rather than calling models directly for the core login flow; other actions (`face_register`, `changePassword`, `forgotPassword`, `resetPassword`, `otp`, `login_otp`) still query models inline. Validation via `Validator::make()` (not FormRequests). Response convention here: **always `200`** on validation/business failure with `{success: false, message: ...}` (only real server errors use `500`) — this is deliberate for the mobile client, don't "fix" it to use 4xx. JWT issued via `auth('api')->login($user)`; single-device-login enforced by overwriting `users.last_login_token` and comparing it to the `Device-Token` header (`CheckSingleDeviceLogin` middleware) on subsequent requests.

Common cross-cutting patterns worth reusing:
- `decrypt($id)` / route-model IDs are often passed **encrypted** in web routes (e.g. `DepartmentController::detail($id)` does `decrypt($id)` before querying) — check for this before assuming a raw integer ID.
- Tenant-scoped uniqueness: `Rule::unique('table', 'col')->where(fn ($q) => $q->where('tenant_id', session('tenant_id')))` — repeat this pattern for any new tenant-scoped unique validation, plain `unique:table,col` is NOT tenant-safe.
- Account-enumeration-safe messaging (`forgotPassword()` always returns the same success message regardless of whether the email matched) — follow this pattern for any new "did this identifier exist" flow.

## API response conventions (two different eras coexist)

- **Legacy/mobile API** (`routes/api.php`, `Api/*` controllers, consumed by the Flutter app): raw `response()->json([...], $code)`, no fixed envelope, historically `200` even for business failures (see above). **Frozen** — do not change shapes here (`ApiV1EnvelopeTest` guards the boundary from the other side).
- **Tier 2 public API** (`routes/api_v1.php` → `/api/v1/*`, `Api/V1/*` controllers): fixed envelope via `app/Http/ApiResponse.php` — success `{"data": ..., "meta": {"request_id": ...}}` (`ApiResponse::ok()`/`::paginated()`), failure `{"error": {"code", "message", "details"}, "meta": {"request_id"}}` (`ApiResponse::fail()`, proper HTTP status codes). Errors are also centrally mapped in `bootstrap/app.php`'s `withExceptions()` via `App\Http\ApiExceptionRenderer::handles()/::render()` for anything falling through to the global handler. Use `ApiResponse` for any new `/api/v1/*` endpoint; never for `/api/*` (mobile) endpoints.

## Service layer

Services are plain, constructor-less-or-DI'd classes under `App\Services`, generally one responsibility per domain (e.g. `AuthService`, `LeaveService`, `AttendanceSummaryService`, `FeatureService`, `RbacService`, plus a `*NotificationService` per module: `LeaveNotificationService`, `ExpenseNotificationService`, `TaskNotificationService`, `OvertimeNotificationService`, `MeetingNotificationService`, `AnnouncementNotificationService`, `AttendanceNotificationService`, `AttendanceRegularizationNotificationService`, `ExpensePaymentNotificationService`, `RequestNotificationService`). Subfolders (`Services/Payroll/`, `Services/Attendance/`, `Services/Approvals/`, `Services/Asset/`, `Services/Biometric/`, `Services/FieldTracking/`, `Services/Performance/`, `Services/Analytics/`) hold larger, multi-class domains — check there first for payroll/attendance/asset/biometric/field-tracking logic before adding new top-level service files. `Services/Asset/AssetLifecycleService` is the single choke point for the Asset module's full lifecycle state machine (register/assign/accept/return/transfer/repair/damage/retire/dispose) — deliberately not built on `Services/Approvals/ApprovalService` (see `docs/modules.md`'s "Asset Management" section for why), paired with a top-level `AssetNotificationService` following the standard `*NotificationService` dual-channel shape. Controllers inject services via constructor property promotion, e.g. `AuthController::__construct(protected AuthService $service, protected LoginAttemptService $loginAttempts, protected AuthAuditService $audit)`. **Not every module has a service** — many CRUD controllers (Department, Designation, Branch, etc.) talk to Eloquent models directly with no service layer at all; don't assume one exists, check the controller first.

Notable feature/permission services (read these before adding any gated feature or permission check):
- `App\Services\FeatureService` — plan/feature gating (`enabledForCurrentTenant($key)`), backs the `feature:` middleware, the `@feature(...)`/`@endfeature` Blade directive, and `EnsureCustomShiftsEnabled`/`EnsureFieldTrackingEnabled` middleware (which check tenant boolean columns `custom_shifts_enabled`/`field_tracking_enabled` directly rather than going through FeatureService).
- `App\Services\RbacService` — tenant RBAC (`can($user, $module, $action)`, `currentCan($module, $action)`), backs the `permission:` middleware and the `@permission(...)` Blade directive. **Correction (verified 2026-09-16): `permission:` middleware IS applied** — 45+ occurrences across `routes/web.php` (payroll, leave, expense, task, team, overtime, etc.), applied per-route alongside/instead of `role:`. `role:` still gates some routes directly, so both mechanisms are live concurrently — check the specific route, don't assume either one exclusively.

## Repository pattern (honest assessment)

Only 4 files exist total: `AuthRepository`/`AuthRepositoryInterface` (one method, `findByEmployeeId()`, relies on `TenantTrait`'s global scope for tenant safety) and `UserRepository`/`UserRepositoryInterface`. Bound via `app/Providers/RepositoryServiceProvider.php` (`$this->app->bind(Interface::class, Implementation::class)`). This is **not a project-wide pattern** — it covers only auth/user lookups used by `AuthService`. Every other model (Department, Leave, Payroll, Attendance, etc.) is queried directly from controllers/services with no repository indirection. Do not introduce new repositories to match a "pattern" that isn't actually followed elsewhere unless asked.

## Middleware

All registered as aliases in `bootstrap/app.php`'s `withMiddleware()` (Laravel 11 style, no `Kernel.php`). Full list with purpose:

| Alias | Class | Purpose |
|---|---|---|
| `tenant` | `TenantMiddleware` | Resolves + binds current tenant (see [Multi-tenancy](#multi-tenancy-pattern)). |
| `singleLogin` | `CheckSingleDeviceLogin` | Compares `Device-Token` header to `users.last_login_token`; rejects if mismatched (enforces single active mobile session per user). |
| `role` | `RoleMiddleware` | `role:admin,hr` — checks `Auth::user()->role` is in the given list, `abort(403)` otherwise. Web-only, session-based. |
| `redirect.role` | `RedirectBasedOnRole` | On the login route, if already authed, redirects to the role-specific dashboard (`dashboard.admin`/`.hr`/`.manager`/`.employee`). |
| `shifts.custom` | `EnsureCustomShiftsEnabled` | Blocks shift-management routes unless `tenant->custom_shifts_enabled`. |
| `field.tracking` | `EnsureFieldTrackingEnabled` | Blocks field-tracking routes unless `tenant->field_tracking_enabled` (paid add-on). |
| `feature` | `EnsureFeatureEnabled` | `feature:payroll` — generic plan/feature gate via `FeatureService`. |
| `permission` | `EnsurePermission` | `permission:payroll,approve` — tenant RBAC gate via `RbacService`. **Applied on 45+ routes** (payroll, leave, expense, task, team, overtime, requests, etc. — `requests`' manager-facing routes migrated from ad hoc `role ==` checks 2026-09-17); `role:` still gates other routes directly, both are live concurrently. |
| `apiv1` | `ApiV1` | Forces `Accept: application/json`, stamps `X-Request-Id` — applied to the whole `/api/v1/*` group. |
| `apikey` | `ResolveApiClient` | Authenticates Tier 2 API requests via `Authorization: Bearer <key_id>.<secret>` against `ApiClient`, binds `current_tenant`/`current_api_client`, logs to `ApiRequestLog`. |
| `idempotency` | `Idempotency` | `Idempotency-Key` header support for unsafe methods on `/api/v1/*` — replays stored response for a repeat key+body, `409` on key reuse with a different body, 24h TTL, uses `idempotency_keys` table. |
| `scope` | `EnsureApiScope` | `scope:analytics:read` — checks the authenticated `ApiClient` has the named scope. |
| (web, appended globally) | `EnforceImpersonationExpiry` | Runs on every web request; force-logs-out and redirects to the super-admin panel if the current impersonation session is expired/ended (`impersonation_sessions` table, 60-min hard cap). |

CSRF is disabled only for `internal/superadmin/*` (machine-to-machine, shared-secret header auth) via `$middleware->validateCsrfTokens(except: [...])`.

## Routes

Four route files, all wired in `bootstrap/app.php`:

- **`routes/web.php`** (~57KB) — Blade admin panel. Top-level `Route::group(['middleware' => ['tenant']], ...)` wraps everything; public careers pages (`careers/*`) are outside auth. Auth routes (`login`, `login.check` w/ `throttle:login`, `forgot-password`) sit above an `auth` group; inside that, per-role dashboard groups (`auth`, `role:admin|hr|manager|employee`) then a large flat set of `Route::prefix('module')->name('module.')->group(...)` blocks, one per feature module (`attendance`, `department`, `leave-type`, `payroll`, `recruitment`, etc.) — mirrors the `Http/Controllers/{Module}/` folder layout. IDs in URLs are frequently `decrypt()`-ed in the controller.
- **`routes/api.php`** — legacy/mobile JSON API for the Flutter app. `Route::middleware('tenant')->group(...)` wraps everything, then `Route::middleware(['auth:api', 'singleLogin'])->group(...)` wraps authenticated endpoints, with `Route::prefix('module')->group(...)` per domain (`notifications`, `overtime`, `meetings`, `offboarding`, `ai`, `loan`, etc.), mirroring `Http/Controllers/Api/{Module}/`.
- **`routes/api_v1.php`** — Tier 2 public API, mounted at `api/v1` with `name: api.v1.` prefix from `bootstrap/app.php`'s `then:` callback (not from within the file itself). `Route::middleware('apiv1')->group(...)` wraps everything; `/ping` is open, everything else additionally requires `['apikey', 'throttle:api-public', 'idempotency']`; some endpoint groups add `scope:{name}` (e.g. `scope:analytics:read`, `scope:biometric:write`).
- **`routes/console.php`** — Artisan closures/scheduled tasks.

## Auth & authorization summary

- **Web**: session guard (`web`), login via `Auth::attempt`-style flow in the web `AuthController`, role stored as a plain string column `users.role` (`admin|hr|manager|employee`), checked by the `role:` middleware on some routes. A parallel `users.role_id` column is synced by `UserRoleObserver`; `RbacService` + `permission:` middleware + `role_permissions` table are now **live on 45+ routes** (verified 2026-09-16) alongside `role:` — both mechanisms are real gates depending on the route, not a one-or-the-other migration in progress.
- **Mobile/legacy API**: JWT (`auth('api')`), single-device enforcement via `last_login_token` + `singleLogin` middleware, OTP login path (Airtel SMS) as an alternative to password login, both funnel through the same JWT issuance.
- **Tier 2 public API**: not a user at all — API-key/secret pairs (`ApiClient` model) with scopes (`scope:` middleware), rate-limited per-client (`RateLimiter::for('api-public', ...)` in `AppServiceProvider`).
- **Policies**: only `MonthlyPayrollPolicy` exists — don't assume Policy-based authorization is the norm; it isn't, yet.
- Named rate limiters (all in `AppServiceProvider::boot()`): `api-public`, `location-ingest`, `login` (keyed by identifier+IP AND a daily per-IP cap), `otp-request`, `otp-verify`.

## Coding conventions actually observed

- Controllers are grouped into per-module subfolders under both `Http/Controllers/` and `Http/Controllers/Api/` — when adding a new module, follow this pairing (web controller + API controller in matching subfolder names) rather than inventing a new layout.
- Validation: inline `$request->validate([...])` or `Validator::make($request->all(), [...])`, essentially never FormRequest classes (only 4 exist). Follow the existing style unless a FormRequest is specifically justified (e.g. complex, reused validation).
- Mutating actions are almost universally wrapped in `try { ... } catch (Exception $e) { return response()->json(['success' => false, 'message' => '...'], 500); }` — match this for new controller actions.
- Exception handling is centralized in `bootstrap/app.php`'s `withExceptions()`: `AuthenticationException` → JSON `401` or redirect depending on `expectsJson()`; `JWTException` (missing/invalid/expired/blacklisted token) → same `401` shape "so API responses stay identical [to what Sanctum used to return]" (explicit comment, `bootstrap/app.php:57-63`); anything under `/api/v1/*` additionally routed through `ApiExceptionRenderer`.
- Comments in this codebase frequently explain **why**, including security rationale and past-bug context (e.g. the login password-validation comment in `AuthController.php:44-50`, the reset-token-expiry comment at `AuthController.php:294-299`) — when editing nearby code, read these comments, they often encode a fixed bug that's easy to reintroduce.
- Blade custom directives: `@feature('key') ... @endfeature` and `@permission('module','action') ... @endpermission`, both registered in `AppServiceProvider::boot()` via `Blade::if(...)`.
- Model observers (not events+listeners) are the mechanism for cross-model side effects tied to a single model's lifecycle (biometric roster sync, role_id sync, task progress sync) — check `AppServiceProvider::boot()` for the full registered list before adding a new one via events.
