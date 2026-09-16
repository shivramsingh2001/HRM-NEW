# Modules Reference

Functional/business-logic reference for the Laravel HRMS app at `D:\HRMNEW\hrm (3)`. Route line numbers refer to `routes/web.php` (admin panel, Blade) and `routes/api.php` / `routes/api_v1.php` (mobile/external API) as they stood 2026-09-16. Business rules below were read directly from controller/service code — not inferred — but controllers are large (several exceed 1,000 lines) so treat each module section as "verified highlights," not an exhaustive line-by-line spec. Re-grep the cited file before relying on an exact rule for anything payroll- or money-related.

## Cross-cutting concerns (apply across most modules below)

**Multi-tenancy**: every table has `tenant_id`; a `tenant` middleware group wraps all of `web.php`/`api.php`. See `docs/architecture.md` for the resolution mechanism.

**Two permission layers, both in active use, not a clean migration**:
- Coarse: `role:admin`, `role:hr`, `role:manager`, `role:employee` (or comma lists like `role:admin,hr`) — `app/Http/Middleware/RoleMiddleware.php`.
- Fine-grained: `permission:{module},{action}` — `app/Http/Middleware/EnsurePermission.php` → `App\Services\RbacService::can()`. `admin` role always bypasses. Used alongside `role:` in the same route file (e.g. leave, expenses, overtime, meetings, payroll, task approval). Controllers additionally call a `scopeCoversOwner($authUser, $module, $action, $ownerUserId)` helper (seen in `LeaveController`, `ExpenseController`) to check whether the acting manager's scope actually covers the specific employee being approved — this replaced an earlier bug where any manager could approve any employee's request.
- `feature:{key}` — `app/Http/Middleware/EnsureFeatureEnabled.php` → `App\Services\FeatureService::enabledForCurrentTenant()`. Gates whole modules by subscription plan (currently applied to `payroll` and `recruitment` route groups). No tenant context = allow.

**Generic multi-level Approval Workflow Engine** (`App\Services\Approvals\ApprovalService`, Tier 2/T2-A): a tenant can configure a named workflow (`approval_workflows` + `approval_workflow_steps`, managed at Settings → Approvals, `app/Http/Controllers/Settings/ApprovalWorkflowController.php`) for a request type. `ApprovalService::decide($type, $subject, $actor, $action, $remarks)` is called from the *old* single-approver controller code; it returns `null` if no workflow is configured for the tenant (falls through to legacy direct-approve code unchanged), or drives the subject through the configured levels (role / user / reporting_head / department_head approvers, quorum `all` or first-wins, per-level SLA with `auto_approve` / `escalate` / `notify` breach action via a scheduled `tick()`). Registered handlers (`request_type => handler class`) in `ApprovalService::HANDLERS`:
  - `regularization` → `RegularizationApprovalHandler`
  - `overtime` → `OvertimeApprovalHandler`
  - `leave` → `LeaveApprovalHandler`
  - `payroll_revision` → `PayrollRevisionApprovalHandler`
  - `payroll_bonus` → `PayrollBonusApprovalHandler`
  - `payroll_run` → `PayrollRunApprovalHandler`
  Approval delegation (`approval_delegations`, date-scoped) is honored in `currentApprovers()`. Also supports delegate-aware notifications via `App\Notifications\CustomNotification`.

**Notifications**: most write-actions fire an in-app `Notification` (Laravel notifications table) plus FCM push (`App\Services\FirebaseService`) via per-module `*NotificationService` classes (`LeaveNotificationService`, `ExpenseNotificationService`, `OvertimeNotificationService`, `AttendanceNotificationService`, `AttendanceRegularizationNotificationService`, `TaskNotificationService`, `RequestNotificationService`, `MeetingNotificationService`, `AnnouncementNotificationService`, `ExpensePaymentNotificationService`). These are consistently wrapped in `try/catch` so a notification failure never blocks the underlying transaction.

---

## Auth

- **Web**: `App\Http\Controllers\Auth\AuthController` — session login (`/login`), forgot-password. `role:` middleware then routes to one dashboard per role.
- **Mobile/API**: `App\Http\Controllers\Api\Auth\AuthController` — `POST /api/login` (JWT via `auth:api` guard, see `docs/architecture.md`), `POST /api/send-otp` + `POST /api/login-otp` (OTP login, throttled `otp-request`/`otp-verify`), forgot/reset password, change password, face registration flag (`GET /api/user/face-registration`), device logout, account delete (`GET /api/delete`).
- `singleLogin` middleware (`CheckSingleDeviceLogin`) enforces one active session per user on the API guard — routes/api.php:48.
- Super Admin Panel impersonation handoff: `GET /impersonate/consume` (no auth guard — issues a login) and `POST /impersonate/end`, `App\Http\Controllers\Impersonation\ImpersonationController`. `EnforceImpersonationExpiry` middleware presumably time-boxes the session (see `docs/architecture.md` for the full middleware list).
- Login attempts are throttled (`throttle:login`) and logged via `App\Services\LoginAttemptService` / `AuthAuditService`.

## Dashboard

`App\Http\Controllers\Dashboard\DashboardController` — one method per role (`adminDashboard`, `hrDashboard`, `managerDashboard`, `employeeDashboard`), each behind its own `role:` group, plus a generic `/dashboard` that likely redirects by role (see `RedirectBasedOnRole` middleware).

## Users / Employee Profiles

- Controller: `App\Http\Controllers\User\UserController` (web, `routes/web.php:179-200`), `App\Http\Controllers\Api\User\UserController` (mobile: profile view/update, team view, get-country/states/cities, location tracks).
- Multi-step create/edit wizard: `store` → `saveStep` → `completeStore` (create), `updateStep` → `completeUpdate` (edit), plus `loadSavedData` to resume an in-progress wizard.
- Split profile tables per `docs/database.md`: `user_basic_details`, `user_job_details` (designation/department/reporting_head/branch/payroll), `user_bank_details`.
- Toggle endpoints: `toggle-status` (activate/deactivate), `toggle-face-register`, `update-attendance-type`, `toggle-location-tracking` (field-tracking seat, single user), `bulk-location-tracking` (bulk).
- Export: `export/excel`, `export/pdf` (employee list).

## Org Structure: Branches, Departments, Designations, Employment Types

- `App\Http\Controllers\Branch\BranchController` — CRUD, validates `latitude`/`longitude` as `nullable|numeric` within standard ranges. **Note**: no geofence radius field was found on `Branch` model or in the branch validation rules during this pass — if geofencing enforcement exists it is elsewhere (not confirmed in this review; verify before relying on it).
- `App\Http\Controllers\Department\DepartmentController`, `App\Http\Controllers\Designation\DesignationController` — simple CRUD, index/create/update/detail pattern, no delete route exposed for either.
- Employment types: model `EmployementType` exists (note the typo is baked into the class/table name); no dedicated controller found in `routes/web.php` — likely managed as a lookup elsewhere (check Settings or seeders if this needs UI).
- Roles/permissions: `roles`, `role_permissions` tables (per `docs/database.md`); runtime checks go through `RbacService`, not a dedicated CRUD controller found in this pass.

## Attendance

- Web: `App\Http\Controllers\Attendance\AttendanceContoller` (sic — typo preserved from codebase) — calendar view, sessions.
- Mobile: `App\Http\Controllers\Api\Attendance\AttendanceController` (huge, ~1600+ lines) — `clockIn`, `clockOut`, `history`, `trackLocation`/`trackBatch` (GPS breadcrumbs, throttled `location-ingest`), `today`, `todayLocations`, `regularizationStore` + regularization view/approval endpoints.
- **Clock-in rules** (`AttendanceController::clockIn`, `app/Http/Controllers/Api/Attendance/AttendanceController.php:440`): requires `lat`, `long`, `address` (min 5 chars); resolves the user's shift for the day; **night-shift date rollback** — if the shift end time is before its start time (or ends ≤04:00 and starts ≥20:00) and the clock-in happens between 00:00–05:59 before the shift's start hour, the attendance row is dated to the *previous* calendar day; blocks a second clock-in while an open (no `clock_out`) row exists for the resolved date; checks for an approved WFH/Travel `Request` covering the date. Failed attempts are logged via `createFailureLog()`.
- **Late/half-day policy** (`App\Services\Attendance\LatePolicyService::recalculateMonth`) — Feature B, the *sole writer* of `attendances.effective_status`/`day_fraction`/`policy_note`, deterministic/idempotent, re-runnable any time:
  - Manual rows (`attendance_type = manual` or has `marked_by`) are authoritative and never reclassified from worked hours.
  - `on_leave`/`absent`/`holiday`/`weekoff` → 0.0 day fraction; `half_day`/`first_half_leave`/`second_half_leave` → 0.5.
  - Otherwise worked seconds are computed (`AttendanceCalculator::workedSeconds`, capped at 24h) and classified by `AttendancePolicySnapshot::classify()` against the tenant's policy in force **on the 1st of that month** (a later policy change never retroactively re-grades a closed month).
  - Each `late` day increments a per-user monthly late counter; once the count exceeds the tenant's `monthlyLateAllowance` (Settings → Attendance Policy, `role:admin,hr` only, `routes/web.php:532`), the day is downgraded to `half_day` (0.5 fraction) instead of `present` — only if `lateHalfdayEnabled` is on. A day that was regularized is never counted as late and never downgraded.
- Attendance analytics/anomaly review: `App\Http\Controllers\Analytics\AttendanceAnalyticsController`, gated `role:admin,hr,manager`, backed by `App\Services\Analytics\AttendanceAnalyticsService` / `AnomalyScanner`.
- Team-level manual marking + attendance log/summary: `App\Http\Controllers\Team\TeamController` (`routes/web.php:280-297`) — `mark-attendance` restricted to `role:admin,hr,manager`; `attendance-log` gated `permission:team,view`.
- Reports: `App\Http\Controllers\Report\AttendanceReportController` — day/hourly/overall/employee-wise/branch-wise/monthly-summary variants, each with an `export` counterpart, all under `role:admin,hr,manager`.

## Attendance Regularization

- Web: `App\Http\Controllers\Attendance\AttendanceRegularizationController` (`routes/web.php:423-435`) — self-service `store`/`update`/`destroy`; team `manage` + `team-approval` gated `permission:attendance,approve`.
- Mobile: regularization endpoints live inside `Api\Attendance\AttendanceController` (`regularizationStore`, `getMyRegularizations`, `getReporteesRegularizations`, `regularizationApproval`) and a public-API v1 surface (`RegularizationV1Controller`, key-authenticated, `scope:regularization:read`/`write`, `routes/api_v1.php:31-34`).
- Routed through the same `ApprovalService` engine as Leave when a tenant has a `regularization` workflow configured (`RegularizationApprovalHandler`); otherwise falls back to the direct single-approver path.
- A regularized attendance row is excluded from the late-allowance count (see Attendance section above) and is never downgraded to half-day by `LatePolicyService`.

## Overtime

- Web: `App\Http\Controllers\Attendance\OvertimeController` (self-service `overtime.*` routes) + `App\Http\Controllers\Attendance\OvertimeSettingController` (tenant policy, `permission:overtime,manage`).
- Mobile: `App\Http\Controllers\Api\Attendance\OvertimeController`.
- **Business rule** (`OvertimeController::store`, `app/Http/Controllers/Attendance/OvertimeController.php:100`): one request per user per date (duplicate blocked); `overtime_hours` must be 0.5–24 and ≤ tenant's `max_hours_per_day` (from `overtime_settings`, tenant-specific row or a global fallback row with `tenant_id = null`); **auto-approval**: if the tenant's policy has `require_approval = false`, or `overtime_hours <= auto_approve_limit`, the request is created with `status = approved` immediately (no approver step) — otherwise `status = pending`.
- Approve/reject gated `permission:overtime,approve`; view-all/show gated `permission:overtime,view`. Also routed through `ApprovalService` (`OvertimeApprovalHandler`) if a tenant workflow exists.
- `App\Services\Attendance\OvertimeApprovalService::bulkApprove(User $actor, int $tenantId, array $requestIds)` — single source of truth for "bulk-approve these pending requests as this actor": `RbacService::can(...,'overtime','approve')` gate, `scopeFor()` restricts to reportees when scope is `team`, direct status flip (bypasses `ApprovalService`'s multi-level workflow by design — matches this method's own precedent). Used by `OvertimeController::bulkApprove()` (currently **unrouted** — no `web.php`/`api.php` route wires it up, pre-existing dead code, not fixed as part of extracting this service) and by `MonthlyPayrollController::update()`'s "include non-approved overtime" flow below.

## Biometric Device Integration (SBXPC bridge)

- Admin UI: `App\Http\Controllers\Settings\BiometricController` (`routes/web.php:568-585`, `role:admin,hr`) — device CRUD, bridge-key generation, config download, enrollment mapping (manual `mapEnrollment` / `autoMap`), roster sync trigger, card assignment, punch reprocessing/listing.
- Bridge-facing API (key-authenticated, `routes/api_v1.php:43-57`): `POST /api/v1/biometric/punches` (`scope:biometric:write`), device heartbeat, enrollment reporting/listing, and a **roster pull/ack protocol**: bridge does `GET /devices/{serial}/roster` to learn desired user diffs and `POST .../roster/ack` to confirm what it wrote to the terminal.
- `App\Services\Biometric\BiometricRosterService::rebuild()`: **key rule** — the device's `enroll_no` is always `(string) users.id` (never hand-mapped), so punch ingestion resolves straight back to the employee; computes `pending`/`removing`/`targets` diff per device.
- `App\Services\Biometric\BiometricAttendanceService::apply()`: turns one `biometric_punches` row (`status = pending`) into an `attendances` write, routed through `AttendanceEntryService` (which enforces period lock, audit trail, domain event, summary refresh) — described as "a straight lift of the retired ProcessFingerprintPunch" job, i.e. this replaced an older CAMS-based pipeline (consistent with `hrm-biometric-integration` memory).
- Models: `BiometricDevice`, `BiometricEnrollment`, `BiometricPunch`.

## Field Tracking (GPS, paid add-on)

- Settings UI: `App\Http\Controllers\Settings\FieldTrackingController` (`role:admin,hr`) — **seat count and the on/off master switch are vendor/billing-owned and read-only here**; the tenant admin can only adjust ping cadence (`field_tracking_ping_seconds`) and retention (`field_tracking_retention_days`, floored by `config('location.retention_floor_days')`, default 30). Saving requires the `field.tracking` middleware (`EnsureFieldTrackingEnabled`) in addition to the page itself always being reachable so the upsell message can render when off.
- `App\Services\FieldTracking\FieldTrackingService` is the **single source of truth** for seat rules, explicitly shared between the web employee list (bulk/single toggle in `UserController`) and the mobile clock-in path so entitlement logic can't drift between the two. `seatsUsed()` counts active users with `user_job_details.location_tracking_enabled = 1`; `seatsPurchased()` reads `tenant.field_tracking_seats`.
- GPS breadcrumbs land in `attendance_tracks` via the mobile `track`/`track-batch` endpoints (throttled `location-ingest`); usage rollups in `field_tracking_usage` (per `docs/database.md`).

## Leave Management

- Web: `App\Http\Controllers\Leave\LeaveController` (apply/view/update/delete self-service; `view-all` gated `permission:leave,view`; `update-status` gated `permission:leave,approve`), `LeaveTypeController` (CRUD leave types), `LeaveCreditController` (credit engine, below).
- Mobile: `App\Http\Controllers\Api\Leave\LeaveController`.
- **Approval logic** (`LeaveController::updateStatus`, `app/Http/Controllers/Leave/LeaveController.php:580`+): `scopeCoversOwner()` authorizes the approver against the specific employee; a distinct **revoke path** exists for cancelling an *already-approved* leave (`status='approved'` → `cancelled`, via `LeaveService::cancelApprovedLeave`, which restores the balance) separate from deciding a still-pending one. Only `pending` leaves can be decided normally. Routes first through `ApprovalService::decide('leave', ...)` if the tenant has a configured workflow (translating `cancelled`→`rejected` for the engine's vocabulary); if no workflow, falls back to `LeaveService::approvePendingLeave` / `cancelPendingLeave`. A code comment notes this fallback **fixed a prior bug** where the web flow ignored the service's success/failure result, silently committing an "insufficient balance" rejection as if it succeeded.
- Leave status vocabulary: `pending`, `approved`, `cancelled` (used for both employee-initiated cancel and manager rejection).
- **Leave Credit** (`LeaveCreditController`, `routes/web.php:474-484`): scheduled/manual crediting (`process`), manual credit/debit (`permission:leave,manage`), new-joiner proration (`handleNewJoiner`), per-user and self transaction history, reports.

## Holidays

`App\Http\Controllers\Holiday\HolidayController` (web) / `Api\Holiday\HolidayController` (mobile, read-only list). Simple CRUD + a `PATCH .../status` toggle; no approval workflow.

## Payroll

Large module, two generations of code coexisting ("payroll rebuild" phases visible in comments):

- **Legacy/flat engine**: `PayrollMasterController` (payroll master templates), `UserPayrollController` (per-employee flat payroll assignment), `MonthlyPayrollController` (2,500+ lines — monthly payroll generation, payslip PDF via barryvdh/laravel-dompdf, bulk update, export, `calculate-estimates`). Employee-facing payslip self-service: `my-payroll/*` (`permission:payroll,view`) and mobile `GET /api/payslips`, `/payslip/{id}/{check,summary,view,download,base64}`.
- **Dynamic engine (Phase 2+, additive)**: `App\Services\Payroll\PayrollCalculationEngine` computes a full payslip from `PayrollComponentMaster` + `PayrollEmployeeComponent` in two passes. **Correction (verified 2026-09-16, live DB): this engine IS live**, despite its own docblock claiming otherwise — `MonthlyPayrollController::store()` branches on `tenants.payroll_dynamic_ui_enabled` and calls it directly; tenant 7 has this flag on with real `dynamic_v1` payroll runs in `monthly_payrolls`. Don't treat this engine as dead/inert code. — Pass 1 resolves earnings/employer contributions (percentage components can reference an already-resolved component because component `priority` ordering is enforced at save time); Pass 2 resolves deductions, where a `gross_pass1` calculation base reads Pass 1's finalized total. Each component prorates using its *own* `proration_rule` against a shared attendance context, explicitly contrasted against the legacy engine's "one blanket proration factor for everything."
- `PayrollComponentController`, `PayrollStructureController`, `PayrollEmployeeStructureController` — manage the dynamic component catalog and per-employee structures; writes go through `App\Services\Payroll\PayrollStructureAssignmentService`, which reuses the same approval/revision-log/arrears wiring as everything else in this module rather than duplicating it, and maps legacy flat fields (`basic_salary`, `hra`, `conveyence`, `medical_allowance`, ...) to catalog component codes for backward compatibility.
- `App\Services\Payroll\PayrollArrearsCalculator`: when a salary revision's `effective_from` is backdated into an already-paid month, recomputes what the new structure *would have* paid and queues the delta as a `payroll_arrears` row (positive = owed to employee, negative = to recover), comparing against the legacy engine's persisted `monthly_payrolls` figures ("actually paid" ground truth) — only for a structure that is genuinely current/active, never for draft/pending/rejected revisions.
- `App\Services\Payroll\LoanDeductionService`: single source of truth for "how much loan to deduct this month," explicitly replacing two previously-duplicated raw-SQL implementations; unlike the old code it handles **both** EMI and lumpsum repayment types (the old filter silently excluded lumpsum loans from payroll).
- `PayrollBonusController` — bonus requests with `decide` (`approve|reject` via route constraint), routed through `PayrollBonusApprovalHandler`.
- `PayrollArrearsController` — list + `cancel`.
- `PayrollEngineSettingsController` — engine-level settings.
- `StatutoryComplianceController` — PT slabs and statutory rate config (`StatutoryPtSlab`, `StatutoryRateConfig`).
- Entire `payroll-*` route family is gated `feature:payroll` (tenant-plan check). **As of the Phase 1 audit-fix pass (2026-09-16), every route in every payroll-related group is additionally gated `permission:payroll,{view|create|edit|delete|approve|manage}`** — previously only `monthly-payrolls`/`my-payroll` had this; `payroll-masters`, `employee-payrolls`, `payroll-components`, `payroll-structures`, `payroll-employee-structures`, `payroll-compliance`, `payroll-bonuses`, `payroll-arrears`, `payroll-engine-settings` had no user-permission check at all (any authenticated tenant user could reach them). `payroll-bonuses.decide` specifically requires `payroll,approve` — closes a real self-approval gap when a tenant has no `ApprovalWorkflow` configured for `payroll_bonus` (the request-level fallback in `PayrollBonusController::decide()`/`store()` is otherwise unauthenticated-by-role).
- **Payroll audit fixes (2026-09-16, Phase 1/Critical only — see `tests/Feature/PayrollAuditPhase1Test.php`)**: (1) Edit Payroll's `update()` was silently dropping `tds` from totals on every save — fixed, now handled identically to `create()`. (2) `destroy()` and `store()`'s `force_reprocess` bulk-delete now call `LoanDeductionService::revokeForPayroll()` before removing a payslip, so the loan ledger can no longer be left orphaned/desynced. (3) `force_reprocess` now goes through the same `MonthlyPayrollPolicy` check `destroy()` uses (blocks itemized, doesn't partially process) instead of a raw unguarded mass-delete. (4) `store()`/`update()`/`destroy()` now check `PeriodLockService::isLocked()` — inert until a deploying admin sets `ATTENDANCE_PERIOD_AUTOLOCK=true` (default off), and there's still no "reopen" UI (`PeriodLockService::reopen()` exists but nothing calls it — deferred). (5) `payroll_components.component_type` enum grew `'employer_contribution'` (migration `2026_09_17_000011_...`) — previously silently truncated to `''` on insert since the enum only allowed `earning`/`deduction`. Calculation-accuracy findings (attendance/payroll classification mismatch, legacy engine's manual-only statutory deductions, etc.) are intentionally **not** part of this pass — see the Payroll Audit report for the full list and remaining phases.
- **Overtime ↔ Payroll integration** (Edit Payroll, `MonthlyPayrollController::edit()`/`update()`): the approved-OT `overtime_hours`/`overtime_amount` fields stay fully editable as before. Additionally, `edit()` now also fetches that employee's **pending** `overtime_requests` for the payroll month and (if `RbacService::can($user,'overtime','approve')`) shows an "include non-approved overtime" checkbox with a system-computed hours/amount preview. Checking it and saving: server re-validates the submitted request IDs against tenant/user/month/`status=pending` (never trusts the client), bulk-approves them via `OvertimeApprovalService::bulkApprove()` (direct approve, bypasses any multi-level workflow — same precedent as `bulkApprove()`), and **recomputes** the newly-approved delta server-side via `overtimeAmountForHours()` before folding it into `overtime_hours`/`overtime_amount` — the client's number for that delta is never trusted, only used for the live totals preview pre-save.
- `MonthlyPayrollController::overtimeHourlyRate()` / `overtimeAmountForHours()` (private helpers) are the **single source of truth** for OT pricing — used by live payroll generation, the Edit Payroll pending-OT preview, and the post-approval recompute, so they can't drift from each other. Day-based rate = `(basic_salary / daysDivisor) / working_hours_per_day` where `daysDivisor` is calendar days in the month (default) or a tenant-configurable fixed count (`payroll_masters.ot_rate_divisor_mode`/`ot_fixed_working_days`, see Phase 2 below); hour-based = `basic_salary / (workingDays * working_hours_per_day)`; both × the tenant's real `overtime_settings.rate_multiplier` (previously the Edit Payroll JS preview hardcoded `1.5` and a `/8` divisor regardless of tenant settings — fixed as part of this integration).
- **Payroll audit fixes (2026-09-16, Phase 2/Calculation-accuracy — see `tests/Feature/PayrollAuditPhase2Test.php`)**:
  - **H1**: `PayrollComponentMaster`'s save-time validation now also rejects an earning/employer_contribution/reimbursement component (Pass 1) based on a deduction component (Pass 2) — previously only priority ordering was checked, so this configuration passed validation and silently resolved to ₹0 at calculation time.
  - **H2**: `PayrollEmployeeStructure::supersedeOthers()` now only closes a prior structure when it genuinely started *before* the incoming revision — a backdated revision no longer writes an inverted (`effective_to < effective_from`), permanently-unreachable row. One already-corrupted local row (`payroll_employee_structures` id 147) was repaired.
  - **C2/H7**: `getAttendanceStatusByShift()` (used by both `calculateDayBreakdownWithPriority()` and `calculateAttendanceSummary()`) now delegates to `App\Services\Attendance\PolicyResolver`/`AttendancePolicySnapshot::classify()` — the same tenant-configurable policy the Attendance module itself uses — instead of hardcoded 60%/20%/2h/6h thresholds. `calculateAttendanceSummary()`'s overtime-hours detection also now uses the tenant's real `payrollMaster->working_hours_per_day` instead of a hardcoded `8.0`. **Behavior change**: for attendance rows with no recorded shift start/end, present/half/absent classification can shift (old fallback was 2h/6h; new default policy fallback is 4h/8h, tenant-configurable via `attendance_policies`).
  - **H6**: the sandwich rule (`calculateDayBreakdownWithPriority()`) now treats `'paid_leave'` the same as `'present'`/`'half_day'` when deciding whether a flanking day counts as "worked" — a weekoff/holiday flanked by approved paid leave is paid, not just one flanked by present/half-day attendance.
  - **M8**: day-based OT rate's divisor is now explicit and tenant-configurable (`payroll_masters.ot_rate_divisor_mode`: `calendar_days` default (unchanged) or `fixed_working_days` + `ot_fixed_working_days`), surfaced on the Payroll Master create/edit forms.
  - **M1**: the Statutory Compliance PT-slabs screen now shows a clear warning when no PT slabs are configured for the tenant, since PT was previously silently falling back to manual entry with no indication of why — no real PT slab data was fabricated (that's real per-state government data this session had no access to).
  - **C7 — dynamic engine migration**: `PayrollCalculationEngine` had a materially more complete migration path already built (from an earlier "Payroll rebuild Phase 1–9" effort) than this doc previously described — `payroll:backfill-component-catalog`, `payroll:resync-drifted-structures`, and `payroll:engine-diff` Artisan commands, plus `PayrollStructureAssignmentService` dual-writing into `payroll_employee_structures` once `tenants.payroll_dynamic_ui_enabled` is on. New `app/Console/Commands/PayrollCutoverTenant.php` (`payroll:cutover-tenant {tenant} [--dry-run]`) is the single safe on-ramp for flipping that flag — runs the catalog/drift checks and an engine-diff parity check first, refuses on any failure, never touches historical `monthly_payrolls`. Full rollout guidance (including for the 10 real production companies this doesn't have direct access to): [`docs/payroll-dynamic-engine-migration.md`](payroll-dynamic-engine-migration.md).
  - Calculation-accuracy items intentionally **not** part of this pass: **C7's legacy-engine-side work** (this pass only builds the safe cutover *tool* — actually flipping the flag for any given tenant, local or production, is a deliberate decision left to the user), full real PT slab data (M1), H1-class validation is model-level only (no DB CHECK constraint — MySQL can't express it relationally, matches the existing priority-ordering precedent). See the Payroll Audit report for the full list and remaining Phase 3/4 items.
- **Payroll audit fixes (2026-09-16, Phase 3/Secondary-surface consistency — see `tests/Feature/PayrollAuditPhase3Test.php`)**:
  - **H3**: `generatePayslip()`'s PDF (`pdf.blade.php`) previously never referenced `->components` at all, and had no row at all for `special_allowance` or `overtime_amount` — both fixed; the PDF now also renders any component with no dedicated fixed column (custom bonuses/arrears/dynamic-engine-only rows), mirroring `show.blade.php`'s exact filter pattern so the two can't diverge again.
  - **H4**: `index()`'s stat tiles ("Total Payroll", "Paid Amount", "Progress") now aggregate the full filtered query (cloned before `->paginate(15)`) instead of summing the already-paginated Collection — previously silently wrong for any filter matching more than one page. Several other tile variables in that view (`$totalGross`, `$pendingCount`, etc.) had the identical bug but were dead code (never rendered) — removed rather than fixed, since nothing displayed them.
  - **H5**: `PayrollMasterController::store()`/`update()`'s master-name uniqueness check is now tenant-scoped (`Rule::unique('payroll_masters','name')->where('tenant_id', ...)`) instead of global — two unrelated tenants can now both name a master "Standard".
  - **M2**: removed the dead `viewPayslip()` method and its `GET /{id}/view-payslip` route — it rendered a Blade view (`pdf-payslip.blade.php`) that never existed and wasn't linked from anywhere; `generatePayslip()`/`payslip` remains the one working payslip-PDF route.
  - **M3**: uncommented `let workingDaysCache = {}` in `create.blade.php` — the "Process New Month" calculation-summary preview was throwing an uncaught `ReferenceError` on every month selection, silently never showing.
  - **M4**: `PayrollMasterController::store()`/`update()` now enforce the same ≤100% earnings/deductions cap server-side (`Validator::after()`) that the form's JS already enforced client-side only — a direct POST bypassing the browser could previously save an invalid master.
  - **M6**: `PayrollArrearsCalculator::computeForRevision()` now logs (via `Log::channel('daily')`) whether a backdated revision found *no* processed/paid legacy payslip to reconcile against at all, vs. genuinely reconciling with no material difference — previously both cases looked identical (an empty Collection) from the outside. Return type/contract unchanged; all 3 call sites are fire-and-forget.
  - **H8 — reopen for correction**: new `MonthlyPayrollPolicy::reopen()` (allowed only when `payment_status !== 'pending'`) + `POST /{id}/reopen` (`permission:payroll,manage`, `MonthlyPayrollController::reopen()`) is the deliberate, audited alternative to the unrestricted `force_reprocess` bypass for correcting an already-paid payslip: validates a required `reason`, checks the same `PeriodLockService::isLocked()` gate `update()`/`destroy()` use, flips `payment_status` back to `'pending'`, and writes an explicit `PayrollAuditLog` row (`action => 'reopened'`) capturing the reason. No new loan/OT-reversal logic was needed — `update()` already idempotently resyncs both on every save, so reopening just unlocks the existing, already-correct edit flow. UI: a "Reopen for Correction" button + reason modal on `show.blade.php`, visible only when `$canReopen` (policy **and** `payroll,manage` permission, computed server-side in `show()`).
- **Payroll audit fixes (2026-09-16, Phase 4/Hygiene & hardening — final phase, see `tests/Feature/PayrollAuditPhase4Test.php`)**:
  - **L1**: `payroll_masters`'s 12 monetary columns migrated from `double` to `decimal(15,2)`, matching every other money column in this schema.
  - **L2**: `payroll_runs` now has a real `unique(payroll_period_id, run_number)` constraint — previously only an app-computed `count()+1` guarded against duplicate run numbers.
  - **L3**: `statutory_pt_slabs`/`statutory_rate_configs` now have real unique constraints (`tenant_id`+`state_code`+`gender`+`gross_salary_min`+`effective_from`, and `tenant_id`+`statutory_type`+`region_code`+`effective_from`, respectively) instead of just a plain index. Caught during this pass: `region_code` was nullable with no default, and MySQL/MariaDB treat NULL as distinct in a unique index — the constraint silently didn't catch duplicates whenever `region_code` was NULL (the common, non-region-specific case). Normalized to `NOT NULL DEFAULT ''` so the constraint actually works.
  - **L6**: `savePayrollComponents()`'s `$amount > 0` guard is now `$amount != 0` — a negative manual-adjustment component (e.g. a clawback) now gets its own line-item row instead of being silently dropped while still counting toward the persisted totals.
  - **L7**: `update()` now `lockForUpdate()`s the payroll row before mutating it (two concurrent edits of the same payslip can no longer interleave); `create.blade.php`'s `basicEstimation()` fallback (shown only when the real `calculate-estimates` AJAX call fails) is now visibly labeled "rough approximation — live calculation unavailable" instead of rendering identically to a real calculated figure.
  - **C9**: `PayrollCalculationEngine`'s docblock and `docs/modules.md`'s "Summary: what's surprising" section both still claimed the dynamic engine was "not yet wired into any live payroll run" — corrected in the code itself (the Payroll section above and `CLAUDE.md` were already fixed in earlier phases).
  - **M9**: the Show Payroll page's action buttons (Generate Payslip, Update Status, Edit Payroll, Back to List) had been commented out since this repo's very first commit, with no history explaining why — confirmed with the user and restored; the modal/JS backing them was already fully functional.
  - **M10**: new `App\Traits\ResolvesCurrentTenant` (container-bound tenant first, then the authenticated user's own `tenant_id`, then `session('tenant_id')` as a last resort) replaces both `MonthlyPayrollController`'s private `currentTenantId()` and the mobile API's `Api\Payroll\PayrollController`'s inline `$user->tenant_id ?? session('tenant_id')` — one canonical implementation instead of two.
  - This closes out all four phases of the Payroll Module Audit (2026-09-16). Every finding (Critical/High/Medium/Low) has been addressed except full real PT slab rate data (M1 — genuinely unavailable to this session) and C7's legacy-engine-side statutory calculation (deliberately superseded by the dynamic-engine migration path instead, per [`docs/payroll-dynamic-engine-migration.md`](payroll-dynamic-engine-migration.md)).

## Expense Management

- `App\Http\Controllers\Expense\ExpenseController` (self-service + `view-all` gated `permission:expenses,view`, `update-status` gated `permission:expenses,approve`), `ExpenseTypeController`, `PaymentController` (advance/settlement payment processing, AJAX-modal driven), `ProjectExpenseController`.
- **Three `requirement_type`s with different approval behavior** (`ExpenseController::updateStatus`, `app/Http/Controllers/Expense/ExpenseController.php:727`):
  - `advance` — approving only marks `status=approved`; balance is credited separately when a *payment* is created (`PaymentController`).
  - `settlement` — approving runs `handleSettlementApproval()`, which deducts the balance immediately inside the same transaction; a caught exception rolls back and surfaces the real error message to the caller.
  - `reimbursement` — approving marks `status=approved`; actual reimbursement happens when a payment is later processed.
  - Only `pending` expenses can be decided; ownership is checked via `scopeCoversOwner()`, replacing a prior bug where the manager-ownership check was unreachable dead code and any manager could approve any employee's expense.
- `expense_budgets`, `expense_transactions`, `expense_status_histories`, `expense_attachments` back the module per `docs/database.md`.

## Loan

- `App\Http\Controllers\Loan\LoanCategoryController` (categories + EMI calculator), `App\Http\Controllers\Loan\LoanController` (requests, approvals, reports).
- **Status lifecycle** (`LoanController`, `app/Http/Controllers/Loan/LoanController.php:469-650`): request → `approve` (guarded by `Loan::canBeApproved()`; on approval, generates either a lumpsum repayment row or a full EMI `generateRepaymentSchedule()` depending on `repayment_type`) → `disburse` (guarded by `Loan::canBeDisbursed()`, sets status to `STATUS_ACTIVE`) → repayments deducted monthly via `LoanDeductionService` (Payroll module). `reject` only allowed while `isPending()`; sets `STATUS_DEFAULT` with a required `rejection_reason`. `cancel` and `processLumpsumPayment` also exist.
- Approvals restricted to `role:admin,hr`; reports to `role:admin,hr,manager`.

## Projects & Tasks

- `App\Http\Controllers\Project\ProjectController` — CRUD + member listing.
- `App\Http\Controllers\Task\TaskController` — assigned-by-me/assigned-to-me lists, create/edit/update, comments, attachments, bulk status update.
- **Task approval** (`TaskController::TaskApproval`, `app/Http/Controllers/Task/TaskController.php:1103`, gated `permission:tasks,approve`): only the original assigner (`TaskPermissionService::isTaskAssigner()`) may approve/reject, and only once the task's own status is already `completed`. For a `task_mode = group` task, one `TaskApproval` record is written per member whose `individual_status = completed` (not just the row that triggered the call), so a group task's approval history reflects who actually did the work.
- `DailyReport` model backs a daily-report feature (no dedicated controller found in this pass — check if this is written from `TaskController` or a scheduled job if extending it).

## Recruitment / ATS

- `App\Http\Controllers\Recruitment\JobOpeningController` (1,200+ lines — the bulk of the pipeline logic) + `App\Http\Controllers\Recruitment\RecruitmentController` (public careers page: `careers.*` routes, no auth).
- **Application pipeline** (`current_stage` on `JobApplication`, transitions via `moveToStage()`): `application_received` → `cv_shortlisted` (`shortlist()`, only from `application_received`) → `interview_scheduled` (`scheduleInterview()`) → `interview_completed` (after `submitFeedback()`) → offer released (`releaseOffer()`) → `offerAccepted()`/`offerRejected()` → `hire()`. `reject()` is allowed from `application_received`, `cv_shortlisted`, `interview_scheduled`, or `interview_completed`, landing on `cv_rejected` (if rejected pre-shortlist) or `rejected` otherwise.
- Every stage transition sends a candidate-facing email (`Mail::to($candidate->email)->send(new ApplicationShortlistedMail/...)`) wrapped in try/catch so a mail failure never blocks the pipeline transition.
- Supporting models: `Candidate`, `CandidateDocument`, `JobOpening`, `JobApplication`, `JobApplicationNote`, `JobApplicationStatusLog`, `JobOffer`, `Interview`, `InterviewRound`, `InterviewSchedule`, `InterviewFeedback`, `RecruitmentStage`, `RecruitmentWorkflowLog`.
- Entire module gated `feature:recruitment`.
- **No standalone Onboarding module/controller was found** in this codebase despite an older memory note referencing `onboarding_tasks` tables — if building onboarding features, verify current DB state in `docs/database.md` rather than assuming those tables are wired to any controller.

## Offboarding

- `App\Http\Controllers\offboarding\OffboardingController` (~900 lines, `routes/web.php:637-675`) + mobile `Api\Offboarding\offboardingController` (employee-facing: notice period, my-requests, show, store).
- **Two-stage review, strictly sequential** (`app/Http/Controllers/offboarding/OffboardingController.php:340-430`): `managerReview()` (approve/reject, only while `manager_review_status = pending`) must be `approved` before `hrReview()` can run at all (`hrReview()` explicitly checks `manager_review_status !== 'approved'` and bails otherwise); a manager rejection immediately sets the parent `status = rejected`. HR approval requires `last_working_date` and, on approval, both finalizes `status = approved` **and** writes `leaving_date` onto the employee's `user_job_details` row.
- Downstream steps after HR approval: knowledge-transfer start/complete, asset-clearance update, exit-interview store/update (`ExitInterview` model), final-settlement process/mark-paid, then `complete`/`reject`/`cancel` on the overall request, plus a free-text remarks endpoint.

## Performance

- `App\Http\Controllers\Performance\PerformanceController` (self/individual/team dashboards, team export) + `App\Http\Controllers\Performance\ManagerPerformanceReviewController` (535 lines — manager-authored reviews: store/show/update/destroy/acknowledge/export, `performance.reviews.*`).
- `App\Services\Performance\PerformanceCalculationService::calculateForUser()` computes, per user per month: attendance score, task-completion score, deadline-met score, regularization score, manager rating, overtime, leave details. **Overall score = simple unweighted average of the first five** (`calculateOverallScore()`, `app/Services/Performance/PerformanceCalculationService.php:519` — despite the surrounding comment referencing "weights," the code takes a plain mean).
- Grade bands (`calculateGrade()`): A+ ≥90, A ≥85, A- ≥80, B+ ≥75, B ≥70, B- ≥65, C+ ≥60, C ≥55, C- ≥50, D ≥45, else F.
- Paid-vs-unpaid leave is resolved from the single authoritative `leave_types.is_unpaid` flag — the code comment notes this replaced a fragile display-name string match that could silently disagree with the payroll and leave-ledger checks elsewhere.

## Meetings / MoM

- `App\Http\Controllers\Mom\MeetingController` (CRUD, `cancel`, `reschedule`, `markAttendance`, all gated `permission:meetings,*`) + `App\Http\Controllers\Mom\MeetingMinuteController` (create/store/reopen minutes for a meeting).
- Mobile equivalents under `Api\Mom\MeetingController`, including a `mom-writer/meetings` listing for whoever is assigned to write minutes.
- Models: `Meeting`, `MeetingParticipant`, `MeetingHistory`.

## Announcements

- `App\Http\Controllers\Announcement\AnnouncementController` — `index` (own), `view-all`, `show`, `acknowledge` (writes `AnnouncementAcknowledgment`); create/update/delete/toggle-status gated `permission:announcements,{create,edit,delete}`; `acknowledgments` list gated `permission:announcements,view`.
- A second, near-duplicate controller exists at `App\Http\Controllers\Auth\Announcement\AnnouncementController` and `App\Http\Controllers\AI\AnnoucementController` (AI/read-only surface) — verify which is actually wired before modifying announcement behavior, since the route file only references the first (`Announcement\AnnouncementController`).

## Requests (WFH / Travel)

- `App\Http\Controllers\Attendance\RequestController` (~1,050 lines) — self-service `store`/`show`/`edit`/`update`/`destroy`, attachments (upload validated to jpg/jpeg/png/pdf/doc/docx, max 5MB, stored under `public/uploads/request/attachments`), stats, export; manager surface at `manager/requests*` (index/show/approve/reject/bulk-approve/export/stats — these specific manager routes are **not** named or middleware-wrapped in the same block, verify auth coverage before exposing further).
- **Overlap rule** (`RequestController::store`, `app/Http/Controllers/Attendance/RequestController.php:121`): a new request is rejected if the user already has a `PENDING` or `APPROVED` request whose date range overlaps (start/end/containment all checked) the new one.
- Status vocabulary is upper-case here: `PENDING`, `APPROVED`, `REJECTED` (distinct from Leave's lower-case `pending`/`approved`/`cancelled` — don't assume the two modules share a status enum).
- `request_types` table defines the type catalog (WFH/Travel per `docs/database.md`); `RequestType` model.

## Reports / Analytics

- `App\Http\Controllers\Report\AttendanceReportController` (day/hourly/overall/employee-wise/branch-wise/monthly-summary, each with an `export` route) and `App\Http\Controllers\Report\TaskReportController` — both under `role:admin,hr,manager`, `routes/web.php:727-749`.
- `App\Http\Controllers\Analytics\AttendanceAnalyticsController` — dashboard + anomaly review (`role:admin,hr,manager`), backed by `AttendanceAnalyticsService`/`AnomalyScanner`.
- Public API v1 analytics (key-authenticated, `scope:analytics:read`): `present-now`, `trends`, `overtime-cost`, `anomalies` (`App\Http\Controllers\Api\V1\AnalyticsV1Controller`).
- AI read-only surface: `App\Http\Controllers\AI\*` — a family of `view_ai_all()` endpoints under `/api/ai/*` exposing near-read-only snapshots (attendance, requests, profile, announcements, project, task, expense, holiday, shift plan, regularization, team, leave) — appears purpose-built for an AI assistant/agent integration rather than the Flutter app.

## Notifications

- In-app: `notifications` table (Laravel's built-in), `App\Http\Controllers\Api\Notification\NotificationController` (index, unread-count, mark read/all-read, destroy/destroy-all).
- Push: `App\Services\FirebaseService` (FCM), tokens managed via `App\Http\Controllers\Api\FCM\FCMController` (`store`/`remove` token) and stored on `users.fcm_tokens` (JSON) per `docs/database.md`.
- Email: SMTP (Hostinger, per prior memory), used for recruitment pipeline mail and presumably payslip/report mail (verify `App\Mail\*` before assuming coverage).

## Settings (Tier 2 platform features)

All under `role:admin,hr`, `routes/web.php:550-591`:
- **Approval Workflows**: `App\Http\Controllers\Settings\ApprovalWorkflowController` — CRUD for `approval_workflows`/`approval_workflow_steps` and `approval_delegations` (see the Approval Workflow Engine cross-cutting section above).
- **API Clients**: `App\Http\Controllers\Settings\ApiClientController` — issues/rotates/revokes keys used by the `apikey` middleware guarding `/api/v1/*` (`ResolveApiClient`, `EnsureApiScope` middleware).
- **Webhooks**: `App\Http\Controllers\Settings\WebhookController` — endpoint CRUD + a `test` trigger; delivery tracked in `WebhookDelivery`/`WebhookEndpoint` models.
- **Biometric**, **Field Tracking**, **Shift Settings**, **Attendance Policy** — see their own sections above.
- **Internal**: `App\Http\Controllers\Internal\FeatureCacheController::bust` — a machine-to-machine endpoint (no user auth shown at that route) the separate Super Admin Panel app calls to invalidate this app's feature-flag cache after a plan/feature change (see `hrm-superadmin-panel` memory for the other side of this integration).

---

## Summary: what's surprising / worth knowing before extending this app

1. Two approval mechanisms coexist per module (legacy direct single-approver code + the generic `ApprovalService` multi-level engine) — always check whether a tenant workflow is configured before assuming which path a request takes.
2. Several controllers carry explicit code-comment confessions of prior bugs that were fixed (Leave web-flow ignoring service result, Expense approval's dead-code ownership check, Loan deduction excluding lumpsum) — the comments are a good signal of where subtle logic previously broke and are worth reading in full before refactoring those areas.
3. Payroll has two calculation engines live in the codebase simultaneously (legacy flat + dynamic component-catalog "Phase 2" engine) — the dynamic engine **is** live for any tenant with `tenants.payroll_dynamic_ui_enabled = 1` (its docblock used to claim otherwise; corrected 2026-09-16). Always check that flag before assuming which engine produced a given payslip — see [`docs/payroll-dynamic-engine-migration.md`](payroll-dynamic-engine-migration.md).
4. No dedicated Onboarding controller/module exists despite it appearing in earlier project notes — don't assume `onboarding_tasks`-style functionality is live without checking `docs/database.md` and re-grepping the codebase.
5. Status vocabularies are **not** consistent across modules: Leave uses lower-case `pending/approved/cancelled`; Requests (WFH/Travel) uses upper-case `PENDING/APPROVED/REJECTED`; Loan/Expense have their own sets. Never assume one module's status strings apply to another.
