# Database Reference

Live MySQL/MariaDB database, **not** fully described by `database/migrations/`. This doc is the authoritative schema reference — read it before writing any query, migration, or model touching the DB, instead of re-dumping the schema.

- **Active DB**: `hrm_22_04` (XAMPP MySQL at `/d/xamp/mysql/bin/`, `mysql.exe -uroot -h127.0.0.1`).
- **Table count**: **148 tables**, all `InnoDB`, all UTF8MB4 (json columns use `utf8mb4_bin` + `CHECK (json_valid(...))`).
- **Migrations coverage**: `database/migrations/` has ~94 files. Older core tables (`attendances`, `leaves`, `users` + profile tables, `departments`, `designations`, `projects`, `tasks`* prior to Sep-16, `shifts`, `holidays`, `expenses`, recruitment/onboarding tables, etc.) were **created directly in the DB and have no migration** — do not expect `migrate:fresh` to rebuild them. Everything added from **2026-08-30 onward** (attendance policies/anomalies/period-locks, biometric v2, the new payroll engine, tasks/projects, MoM/meetings, approval workflows, webhooks/API clients) **does** have a proper migration and is safe to evolve with `php artisan make:migration`.
- **Foreign keys ARE enforced at the DB level for most newer/rebuilt tables**: 222 `FOREIGN KEY` constraints exist across 84 of the 148 tables (verified via `mysqldump --no-data`). The other 64 tables (mostly the un-migrated legacy ones, e.g. `attendance_locations` — renamed from `branches` 2026-09-20, still no FK constraints of its own — `departments`, `shifts`, `holidays`, `companies`-adjacent lookups) rely on **app-level relationships only** (Eloquent `belongsTo`/`hasMany`, 302 relationship methods across 83 model files) with no DB constraint. Don't assume referential integrity on those — validate before deleting parents. (`company_branches`, added 2026-09-20 alongside the rename, is a genuinely new table with a real FK on `branch_head`.)
- **Multi-tenancy**: `tenant_id` appears on the large majority of tables (277 occurrences of the column/index name in the dump). Global/platform tables are **not** tenant-scoped: `tenants`, `companies` (nullable tenant_id for the SaaS-level default), `subscription_plans`, `feature_registry`, `super_admins`, `super_admin_notifications`, `sa_*` (super-admin panel tables), `inquiries`, `payment_logs`, `countries`/`states`/`cities`/`languages` (geo master data), Laravel infra tables (`cache`, `jobs`, `sessions`, `migrations`, etc.).

Regenerate this doc's source data any time with:
```bash
/d/xamp/mysql/bin/mysql.exe -uroot -h127.0.0.1 hrm_22_04 -e "SHOW TABLES;"
/d/xamp/mysql/bin/mysqldump.exe -uroot -h127.0.0.1 --no-data --compact --skip-comments hrm_22_04 > schema_dump.sql
```

---

## Core / Tenancy & Super-Admin platform

| Table | Purpose | Key columns |
|---|---|---|
| `tenants` | One row per customer org (the actual "tenant"). | `uuid`, `subdomain`/`custom_domain`, `status` enum(active/inactive/suspended/trial), `subscription_plan_id`, `max_employees`, `settings` JSON, `field_tracking_*`, `payroll_dynamic_ui_enabled`, `late_halfday_enabled`, `custom_shifts_enabled`, `allow_multiple_punches` (added 2026-09-23, default false — see `docs/modules.md` "Attendance — multiple punches per day"), `default_weekoff_days` JSON, `employee_id_prefix` (added 2026-09-30, nullable 2-letter code — see `docs/modules.md` "Employee ID Prefix") |
| `companies` | Legal company record(s) under a tenant (a tenant can theoretically have >1 company; `isdefault` flag). | `tenant_id`, `subdomain`, `gstnumber`/`pannumber`, `settings` JSON |
| `subscription_plans` | Plan catalog (pricing/limits/features) super-admin manages. | `pricing_type` enum(fixed/per_employee_per_day/per_employee_per_month), `features` JSON, `max_employees` |
| `tenant_subscriptions` | A tenant's active/historic plan assignment. Managed from the separate `hrm-superadmin` app — no Eloquent model for this table exists in `hrm (3)`. | `plan_id`, `features_snapshot` JSON, `status` enum(active/trial/expired/cancelled), `max_employees_override` (nullable int; as of 2026-09-22 actually read, by `App\Services\EmployeeCapService`, layered over `tenants.max_employees`) |
| `tenant_default_config` | Per-tenant default shift/working-days/grace config. | `working_days` JSON, `working_hours_per_day`, `grace_minutes` |
| `tenant_feature_overrides` | Per-tenant feature-flag override (on top of plan features). | `feature_key`, `is_enabled`, `overridden_by` |
| `feature_registry` | Master list of feature keys the platform knows about. | PK is `key` (string), `is_active`, `deprecated_at`, `replaced_by` |
| `tenant_exports` / `tenant_purges` | Audit trail of data export/GDPR-purge runs by super-admin. | `row_counts`/`counts` JSON |
| `super_admins` | Platform operators (separate auth from tenant `users`). | `role` enum(superadmin/support/billing), `role_id`, `totp_secret`, `allowed_ips` |
| `super_admin_notifications` | In-app notifications for super-admins. | `data` JSON |
| `sa_roles` / `sa_role_permissions` | RBAC for super-admin panel (module+action grid). | |
| `sa_plan_versions` | Version history of subscription plan changes. | `features` JSON snapshot |
| `sa_platform_metrics` | Daily rollup snapshot (MRR/ARR/tenant counts) for dashboards. | `funnel`/`new_tenants_by_month` JSON |
| `sa_provisioning_runs` | Step-by-step log of automated tenant provisioning. | `steps`/`input` JSON, `failed_step` |
| `sa_tenant_health` | Daily per-tenant health snapshot (logins, seat utilisation). | PK is `tenant_id` |
| `sa_tenant_notes` | Free-text CRM-style notes super-admin adds to a tenant. | |
| `sa_feature_override_templates` | Reusable bundles of feature overrides. | `map` JSON |
| `sa_audit_signatures` | HMAC signature per `audit_logs` row (tamper-evidence). | PK is `audit_log_id` |
| `sa_migrations` | Separate migrations-ran table (the super-admin app has its own connection/tracking against this DB). | |
| `audit_logs` | Cross-cutting audit trail (both tenant actions and super-admin/impersonation actions). | `actor_type` enum(super_admin/tenant_user/super_admin_impersonating/system), `old_values`/`new_values` JSON |
| `impersonation_sessions` | Super-admin "login as tenant user" session tracking. | `session_token`, `end_reason` |
| `inquiries` | Public sales inquiry / lead form submissions. | `status` enum(new→contacted→negotiating→payment_sent→paid→provisioned/lost), `plan_interest` |
| `payment_logs` | Manual payment records collected against an inquiry/tenant (pre-provisioning or renewal). | `payment_mode` enum(upi/bank_transfer/cheque/cash/card) |
| `company_registrations` | Self-serve signup intake (pre-tenant): `name`, `company_name`, `email`, `gst`, `details`. | Model unused in code. Recreated on hrm_22_04 2026-09-30 (migration row existed but table was missing). |

## API platform (external API clients, webhooks)

| Table | Purpose | Key columns |
|---|---|---|
| `api_clients` | External API key/secret pairs issued per tenant. | `key_id`, `secret_hash`, `scopes` JSON, `rate_limit_per_min` |
| `api_request_logs` | Per-request log for API clients (latency/status). | `duration_ms`, `idempotency_key` |
| `idempotency_keys` | Idempotency-Key dedupe store for the external API. | unique `(tenant_id, api_client_id, key)`, caches `response_body` |
| `webhook_endpoints` | Tenant-registered webhook URLs + subscribed events. | `events` JSON, `secret`, `failure_count` |
| `webhook_deliveries` | Delivery attempts/retries for webhook events. | `status`, `next_retry_at`, `response_status` |

## Users & org structure

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Core auth/identity row. Split-profile pattern — most HR data lives in satellite tables below. | `role` (string) + `role_id` (FK to `roles`, newer RBAC layered on top of the legacy string role), `employee_id`, `status`, `fcm_tokens` JSON, `card_number` (biometric card), `must_change_password` (added 2026-09-22, default false — set true only by biometric direct-onboarding auto-create; **not enforced anywhere yet**, no forced-change middleware/UI exists) |
| `user_basic_details` | Personal info: DOB, gender, blood group, statutory numbers (Aadhaar/PAN/UAN/PF/ESIC). Also has 4 **deprecated** legacy document columns (`experience_letter`, `tenth_marksheet`, `twelfth_marksheet`, `highest_qualification_certificate`) kept read-only for one release cycle — new uploads go to `employee_documents` instead. | 1:1 with `users` |
| `user_job_details` | Employment info: designation/department/office_branch, `payroll_master_id`, `attendance_type` (manual_attendance / face_verification / biometric_only — the last blocks app clock-in/out), **`location_tracking_enabled`** (field-tracking seat flag), `type` enum(field/office). `reporting_head` is the **primary** reporting head only (denormalized, kept in sync from `user_reporting_heads` on every employee-wizard save) — for the full multi-head set, use `user_reporting_heads` / `User::reportingHeads()` / `User::scopeManagedBy()`. **`office_branch`** (FK-by-convention to `attendance_locations.id`, `0` = "any location") is for attendance geofencing only; **`branch_id`** (2026-09-20, nullable, no FK) is the separate organizational Branch membership — see `attendance_locations`/`company_branches` below. | 1:1 with `users` |
| `user_bank_details` | Bank account + statutory numbers (UAN/PF/ESI). | 1:1 with `users` |
| `user_reporting_heads` | Multi reporting-head pivot (2026-09-19): `user_id`, `reporting_head_id`, `is_primary`. Source of truth for "who manages whom"; `user_job_details.reporting_head` is a denormalized copy of the primary row for backward compatibility with older single-head read sites. | unique `(user_id, reporting_head_id)`, indexed on `reporting_head_id` for reverse ("my team") lookups |
| `employee_documents` | Dynamic, multi-row employee document uploads (2026-09-19) — replaces the 4 fixed columns on `user_basic_details`. `document_type` is a predefined slug (`App\Models\EmployeeDocument::$documentTypes`) or `other` (+ free-text `document_type_other`), optional `document_name`, `file_path`, soft-deletable. | indexed on `user_id` |
| `user_locations` | Address + geo (country/state/city FK to master tables, lat/long). | |
| `user_shifts` | Per-day shift assignment. Since 2026-09-18, a **generated cache** materialized from `shift_assignments` by `App\Services\Shift\ShiftMaterializer` — not hand-written directly by the assign flow anymore. | `shift_assignment_id` (nullable, no FK — null for pre-2026-09-18 rows or a raw single-day `updateUserShift` edit); **multi-shift (2026-10-02, migration `2026_10_02_000005_allow_additional_shifts_per_day`)**: `is_additional` tinyint(1) default 0 (1 = a 2nd+ shift that day), `primary_slot` STORED generated column `IF(is_additional=0,1,NULL)`; the old unique `(user_id, date)` was replaced by unique `user_shifts_user_date_primary_unique (user_id, date, primary_slot)` — still exactly one primary row per user per day, any number of additional rows (not tenant-scoped). `date` is still varchar(20). |
| `user_weekoffs` | Weekly-recurring or date-range week-off definitions. | `off_type` enum(day_based/date_based) |
| `user_payrolls` | Legacy fixed payroll structure per employee, versioned by `effective_from`/`is_current`. | full earnings/deductions breakdown (HRA, conveyance, PF, ESI, TDS…), `ctc`, `net_salary` |
| `user_expense_balances` | Running wallet balance per employee for advances/settlements/reimbursements. | `current_balance`, `advance_balance`, `settlement_balance`, `reimbursement_balance` |
| `attendance_locations` | Geofenced check-in points (lat/long + `radius` meters + `geofence_enabled` toggle, 2026-09-20) used for attendance location verification. Renamed from `branches` — purely an attendance concept, not organizational structure. `App\Models\AttendanceLocation`. | renamed via migration (was DB-only, no prior migration) |
| `company_branches` | The real organizational Branch (2026-09-20): name, address/city/state/country/postal_code, phone/email, `branch_head` (FK → `users`, nullOnDelete), status. Optional — only used by companies with multiple physical offices; `App\Models\CompanyBranch`. | `branch_head` FK-enforced |
| `departments` | | `department_head` (user id, not FK-enforced) |
| `designations` | | |
| `employement_types` | (sic — typo preserved from original schema) | |
| `roles` / `role_permissions` | Newer per-tenant custom RBAC (module+action+scope grid), layered on top of the legacy `users.role` string. | `role_permissions.scope` enum(own/team/company), `action` enum(view/create/edit/delete/approve/export/manage) |
| `countries` / `states` / `cities` / `languages` | Global geo/lookup master data (not tenant-scoped). | `cities`→`states`→`countries` chained by `*_code`, not `id` |

## Attendance & Biometric

| Table | Purpose | Key columns |
|---|---|---|
| `attendances` | One row per employee per day — the attendance "fact" table, and (since 2026-09-23) a **materialized rollup of `attendance_punches`** rather than a direct write target. `clock_in`=first punch of the day, `clock_out`=last punch (null while a session is open), `worked_hours`=sum of session durations. | `attendance_status` enum(present/absent/half_day/late/early_departure/overtime/on_leave/first_half_leave/second_half_leave/holiday/weekoff), `attendance_type` enum(manual/fingerprint/face/card/app), `location_verification` enum(verified/unverified/out_of_bounds), `clock_in_utc`/`clock_out_utc` + `tz`, `day_fraction`, `session_count` (added 2026-09-23, sessions that day; always 1 for `allow_multiple_punches=0` tenants and pre-2026-09-23 history), `punches_last_synced_at`, `metadata` JSON; multi-shift (2026-10-02): `shift_count` (shifts clocked in for that day), `expected_minutes` (primary shift's paid minutes), `extra_shift_minutes` (minutes worked in additional/2nd+ shifts). **Unique** `attn_tenant_user_date_uq (tenant_id,user_id,date)` (migration `2026_09_09_000001_dedupe_and_unique_attendances`, first applied on dev 2026-10-02 — it now also re-points `attendance_logs/tracks/punches`, `biometric_punches`, `attendance_tracking_sessions` to the kept row before deleting duplicates), plus `(tenant_id,user_id,clock_in_utc)`. |
| `attendance_shift_segments` | Added 2026-10-02 (multi-shift Phase 3, migration `2026_10_02_000006_add_shift_segments_to_attendance`). One row per shift worked on an attendance day, rewritten by `AttendanceRollupService::recompute()` on every punch. | `attendance_id` FK → `attendances` (cascade delete), `user_shift_id` (null = fixed company shift), `shift_id`, `is_additional`, `scheduled_start/end`, `first_in/last_out`, `worked_minutes`, `expected_minutes`, `late_minutes`, `early_departure_minutes`, `overtime_minutes`, `session_count`, `is_open`. Index `(tenant_id,user_id,date)`. |
| `attendance_punches` | Added 2026-09-23. Raw Clock In/Out event log, one row per punch, from every source (mobile GPS, web, manual/admin, biometric, kiosk). `AttendancePunchService::capture()` is the single write funnel; `PunchSessionCalculator` pairs punches into sessions; `AttendanceRollupService` writes the derived day back into `attendances` via the existing `AttendanceEntryService::record()` funnel. See `docs/modules.md` "Attendance — multiple punches per day". | `direction` enum(in/out), `source` enum(mobile_app/web/manual/biometric/kiosk/api/backfill), `status` enum(active/void, soft-cancel only), `session_seq`, `paired_punch_id` (self-referencing), `client_ref` (mobile offline-retry idempotency key, unique per `(tenant_id,user_id,client_ref)`), `attendance_id` (FK-by-convention back to the day's rollup row), lat/long/address/distance_meters/accuracy_meters, device telemetry columns mirroring `attendances`' own clock_in_*/clock_out_* shape. Indexes `(tenant_id,user_id,date,punched_at)`, `(tenant_id,status)`, `(attendance_id)`, `(biometric_device_id,punched_at)`; `user_shift_id` (2026-10-02, index `punch_user_shift_idx`) = the `user_shifts` row the punch was matched to (an `out` inherits its session's). No hard uniqueness on `(user,punched_at,direction)` — duplicate suppression is an application-level debounce window (`config('biometric.dedupe_window_seconds')`), not a DB constraint. Backfilled from every historical `attendances` row on migration (`source='backfill'`). |
| `attendance_logs` | Immutable event log feeding `attendances` (check-in/out, geofence enter/exit, manual adjustment…). High-volume GPS/device metadata table. | `event_type` enum (9 values incl. `geofence_enter/exit`, `device_change`), lat/long/accuracy/speed/bearing, `is_mock_location`, `verification_method`, `before`/`after` JSON diff, `attendance_punch_id` (added 2026-09-23, nullable, links a log row to the specific punch it audits) |
| `attendance_tracks` | **Legacy, frozen** (as of 2026-10-01) — the old GPS breadcrumb trail, superseded by `attendance_tracking_sessions`/`attendance_tracking_points` below. No new writes; kept read-only for one release cycle (old history + `field-tracking:prune`) before removal. | `lat`/`long` (7-decimal precision), composite indexes `(attendance_id,track_time)` and `(tenant_id,user_id,track_time)` — added 2026-09-13 for scale |
| `attendance_tracking_sessions` | Added 2026-10-01. One row per clock-in→clock-out **punch pair** (`session_seq`), not per day — a multi-punch day's lunch-break cycle gets its own row, separating its breadcrumb trail from the next session's. Opened/closed by `App\Services\FieldTracking\TrackingSessionService::onPunchCaptured()`, hooked into `AttendancePunchService::capture()`. See `docs/modules.md` "Field Tracking". | `punch_in_id`/`punch_out_id` (FK-by-convention → `attendance_punches.id`), `attendance_id` (FK-by-convention, backfilled by `AttendanceRollupService::recompute()`), `status` enum(open/closed), `close_reason` enum(clock_out/grace_expired/admin), `point_count`/`last_point_at` (denormalized), unique `(tenant_id,punch_in_id)`, index `(tenant_id,user_id,started_at)` |
| `attendance_tracking_points` | Added 2026-10-01. GPS breadcrumbs belonging to a tracking session. Bulk-inserted (chunked `insertOrIgnore`) by `App\Services\FieldTracking\TrackingPointIngestService` — retry-safe by construction, no manual dedup check. | `session_id` (FK-by-convention), `point_id` (idempotency key — client-sent, or server-derived `sha1(lat|long|track_time)` when omitted), `accuracy_meters`, `speed_mps`/`bearing`/`is_mock_location` (reserved for future route/geofence replay, not yet populated), no `updated_at` (immutable). Unique `(tenant_id,session_id,point_id)`, indexes `(tenant_id,session_id,track_time)` and `(tenant_id,user_id,track_time)` |
| `attendance_summaries` | Pre-aggregated monthly rollup per user (dashboard/report source of truth, avoids re-scanning `attendances`). | unique `(user_id, year_month)`, `stale_at` (invalidation marker), dozens of aggregate counters |
| `attendance_regularizations` | Employee request to fix a missed/incorrect punch. | `status` enum(pending/approved/rejected); `user_shift_id` (nullable, 2026-10-02, migration `2026_10_02_000007_add_user_shift_id_to_attendance_regularizations`) = which of the day's shifts the request corrects (null = primary). Duplicate rule is per date **and** shift. |
| `attendance_anomalies` | Auto-detected suspicious attendance events (see `AnomalyScanner` service). | `type`, `severity` enum(→ low/medium/high implied), `status` enum(open/…), dedupe via unique `(tenant_id, fingerprint)` |
| `attendance_policies` | Tenant-configurable policy: present/half-day thresholds, grace/rounding minutes, OT multiplier, sandwich-leave rule. Versioned by `effective_from`. | `present_ratio`, `half_day_ratio`, `day_classification_enabled` (bool, default 1, added 2026-09-29 — 0 = no hour-based present/half-day/absent scoring, any work = present), `grace_minutes`, `overtime_after_hours`, `sandwich_leave`, and (added 2026-09-22, generalized late-only policy into two independent rule sets) `grace_mode` enum(shift/fixed, default fixed) + `fixed_grace_minutes`, `late_attendance_action`/`early_attendance_action` enum(none/half_day/absent), `late_deduction_enabled`/`early_deduction_enabled` + `late_deduction_multiplier`/`early_deduction_multiplier`, `monthly_early_allowance` (mirrors existing `monthly_late_allowance`); (added 2026-09-30) `late_deduction_mode`/`early_deduction_mode` enum(fixed_amount/half_day/full_day/custom_multiplier, default custom_multiplier — zero-drift for pre-existing rows) + `late_deduction_amount`/`early_deduction_amount` decimal(10,2) nullable (rupee amount for `fixed_amount` mode) — see `docs/modules.md` "Late Arrival / Early Leaving policy" |
| `employee_policy_overrides` | A company policy value changed for ONE employee (Employee 360 → Policies, added 2026-10-02). No row = the company value applies. Read/written only through `App\Services\EmployeePolicyService`. | `tenant_id`, `user_id`, `section` (attendance / overtime / performance / leave), `key` (the company table's column name; for leave `type:{leave_type_id}`), `value` JSON (a scalar, or for leave an object with `allowed` / `credit_value` / `min_notice_days` / `max_consecutive_days`), `set_by`; unique `(tenant_id, user_id, section, key)`. No FK constraints. |
| `tenants` (request limits, added 2026-10-03) | Company-wide request limits (Company Policies → Request limits). NULL = no limit. Read via `App\Services\RequestLimitService`. | `wfh_max_days_per_month`, `wfh_min_notice_days`, `regularization_max_per_month`, `regularization_max_days_back` (all unsigned smallint, nullable). Per-employee values: `employee_policy_overrides` section `requests`; per-employee expense limit: section `expense`, key `monthly_limit`. |
| `attendance_period_locks` | Locks a tenant+month from further attendance edits (pre-payroll lock). | unique `(tenant_id, year_month)`, `status` enum(open/…) |
| `shifts` | Shift master (start/end time, grace, break). | `color_code` for UI; `is_overnight` tinyint(1) default 0 = ends next day (added 2026-10-02, migration `2026_10_02_000004_add_is_overnight_to_shifts`, backfilled from end_time <= start_time); `name` unique per tenant (app-level only — tenant 7 has a duplicate) |
| `shift_assignments` | Added 2026-09-18. Source-of-truth, append-only history of Permanent/Flexible shift assignments — never hard-deleted; `user_shifts` above is generated from this. See [[hrm-shift-management-overhaul-plan]] and `docs/modules.md` "Shift Management". | `type` enum(permanent/flexible), `is_additional` tinyint(1) default 0 (added 2026-10-02 — an "additional shift" assignment that writes `user_shifts.is_additional = 1` rows and never supersedes the primary), `status` enum(active/superseded/ended/cancelled), `superseded_by_id` self-referencing (no FK), `end_date` nullable (null = open-ended, only legal for `permanent`), indexes `(tenant_id,user_id,type,status)` and `(tenant_id,user_id,start_date,end_date)` |
| `overtime_requests` / `overtime_settings` | Employee OT request + tenant OT policy (rate multiplier, auto-approve threshold). | |
| `biometric_devices` | Registered SBXPC biometric terminals per tenant. | `serial_number` (unique), `direction_mode`, `auto_provision`, `provision_scope`, `push_token` (added 2026-09-28, varchar(64) nullable unique — secret path segment of the FkWeb direct-push URL; null = push disabled), `allow_direct_onboarding` (added 2026-09-22, default false — per-device opt-in for auto-creating an HRM employee from an unmatched device enrollment, see `docs/modules.md`) |
| `biometric_enrollments` | Maps a device's local enrollment slot to a platform `user_id`. | unique `(biometric_device_id, enroll_no)`, `sync_state` |
| `biometric_punches` | Raw punch events ingested from devices before being folded into `attendances`. | dedupe unique `(serial_number, enroll_no, punched_at, raw_verify_mode)`, `status` enum(pending/…) |

> Old CAMS fingerprint pipeline (`fingerprint_devices`, `fingerprint_punch_logs`, `device_user_maps`) was **dropped** on 2026-09-14 (`2026_09_14_000001_drop_legacy_fingerprint_tables.php`) and replaced by the `biometric_*` tables above — see [[hrm-biometric-integration]] memory.

## Field Tracking (GPS add-on)

| Table | Purpose | Key columns |
|---|---|---|
| `field_tracking_usage` | Monthly seat-usage rollup per tenant for billing (peak/avg seats used, GPS row volume — now sourced from `attendance_tracking_points`, was `attendance_tracks` before 2026-10-01). | unique `(tenant_id, year_month)`, `enabled_user_ids` JSON |

Per-employee toggle lives on `user_job_details.location_tracking_enabled`; tenant-level config (`field_tracking_enabled`, `field_tracking_seats`, `field_tracking_ping_seconds`, `field_tracking_retention_days`) lives on `tenants`. GPS breadcrumb schema is `attendance_tracking_sessions`/`attendance_tracking_points` (see "Attendance" table above); `config('location.late_point_grace_minutes')` (default 15) governs how long after clock-out a buffered batch can still land in the just-closed session.

## Leave & Holidays

| Table | Purpose | Key columns |
|---|---|---|
| `leaves` | Leave applications. | `status` enum(pending/approved/cancelled), `start_session`/`end_session` enum(session1/session2/fullday) for half-day leave, `source` (`self`; `manual_attendance` = created by manual attendance marking; `on_behalf` = applied by admin/HR from the Employee 360 page) + `applied_by`, `deduct_balance` |
| `leave_types` | Per-tenant leave type catalog with credit/carry-forward/encashment rules. | `credit_type` enum(weekly/monthly/yearly/no), `is_unpaid`, `max_carry_forward`, `is_encashable`, `min_notice_days`, `max_consecutive_days` |
| `leave_balances` | Current balance per user per leave type. | unique `(tenant_id,user_id,leave_type_id)` |
| `leave_transactions` | Ledger of balance changes (credits/debits) — audit trail behind `leave_balances`. | `transaction_type` enum(add/sub), `before_leaves`/`after_leaves` |
| `holidays` | Company holiday calendar. | `start_date`/`end_date` stored as `varchar`, not `date` (legacy) |

## Payroll — two engines coexist

The schema shows **two generations of payroll engine**, distinguished by `monthly_payrolls.engine_version` (`'legacy_fixed'` vs a newer versioned engine):

**Legacy fixed engine** (older, still primary for most tenants):
| Table | Purpose |
|---|---|
| `payroll_masters` | Named payroll templates with fixed allowance fields (HRA, conveyance, medical, PF, ESI, PT…) as flat columns. |
| `user_payrolls` | Employee's versioned snapshot of a payroll structure (effective-dated). |
| `monthly_payrolls` | The actual monthly payroll run output per employee — huge flat table with every earning/deduction as its own column, `payment_status` enum(pending/processed/paid/cancelled), links to `payroll_runs`/`user_payrolls`. | `late_deduction`/`early_deduction` (added 2026-09-22, decimal 15,2, default 0) — persists `App\Services\Payroll\LateEarlyDeductionCalculator`'s output, same shape as `loan_deduction` |
| `payroll_components` | Free-form extra earning/deduction line items attached to a `monthly_payrolls` row. |

**New component-based engine** (built 2026-09-12, tenant opt-in via `tenants.payroll_dynamic_ui_enabled`):
| Table | Purpose |
|---|---|
| `payroll_component_master` | Tenant-defined pay components (earning/deduction/employer_contribution/reimbursement) with formula config: `calculation_method` (fixed/percentage), `calculation_base` (basic/gross_pass1/ctc), wage ceilings, proration rule. `calculation_base_component_id` (single FK) is kept only as a denormalized "primary base" mirror since 2026-09-19 — the authoritative multi-base selection lives in `payroll_component_base_components`. |
| `payroll_component_base_components` | (added 2026-09-19) Multi-base selection for `calculation_base_type = 'component'`: which Earnings components a percentage component (PF/ESIC/PT/TDS, or any Employer Contribution) sums when computing its base — e.g. "12% of Basic + HRA". One row per selected base. `PayrollComponentMaster::baseComponents()`. |
| `payroll_component_templates` | Platform-level starter templates offered to new tenants (not tenant-scoped). |
| `payroll_structures` | Named salary structure (e.g. "Standard CTC") combining components. |
| `payroll_structure_components` | Join table: which components belong to a structure, with per-structure overrides. `override_calculation_base_component_id` (single FK) is dead (never read/written). The multi-base override lives in `payroll_structure_component_bases` instead (added 2026-09-19). |
| `payroll_structure_component_bases` | (added 2026-09-19) Per-structure-template override of which Earnings components a percentage component's base sums (e.g. this template wants "12% of Basic" while another wants "12% of Basic + HRA") — falls back to `payroll_component_base_components` (the catalog default) when empty. `PayrollStructureComponent::baseComponents()`. When a template with its own override is selected while assigning an employee, the Assign/Revise drawer pre-fills from *this* table, not the catalog. |
| `payroll_employee_structures` | Employee's assigned structure, versioned/effective-dated, with approval workflow (`status` enum draft→pending_approval→active→superseded). |
| `payroll_employee_components` | Per-employee component overrides layered on their structure — a SNAPSHOT of the catalog's base-selection fields at assignment time (fixed 2026-09-19: every write path previously hardcoded `calculation_base_type => 'none'` here regardless of the catalog's real config, silently making "percentage of another component" compute ₹0 for every assigned employee). |
| `payroll_employee_component_bases` | (added 2026-09-19) Snapshot of `payroll_component_base_components` onto one `payroll_employee_components` row at assignment time. `PayrollEmployeeComponent::baseComponents()`. |
| `payroll_periods` | Tenant's monthly pay period lifecycle: open→processing→locked→paid→reopened. |
| `payroll_runs` | A calculation run within a period (supports re-runs via `run_number`), with totals and approval link. |
| `payroll_revision_logs` | Full audit diff whenever an employee's structure changes (increment/promotion/correction/transfer). |
| `payroll_arrears` | Backdated pay corrections queued into a future payroll run. |
| `payroll_bonuses` | One-off/recurring bonus records feeding into a payroll run. |
| `payroll_audit_logs` | Generic polymorphic audit log for the new payroll engine (`auditable_type`/`auditable_id`). |
| `statutory_rate_configs` | Tenant-configurable statutory rates (PF/ESI/PT/TDS/LWF) as JSON `config`, effective-dated. |
| `statutory_pt_slabs` | State-wise Professional Tax slab table (India-specific), effective-dated, gender-aware. |

Both engines share `loans`/`loan_repayments` (below) as a deduction source.

## Loans

| Table | Purpose | Key columns |
|---|---|---|
| `loans` | Employee loan record. | `repayment_type` enum(emi/lumpsum), `status` enum(pending/approved/active/closed/default/cancelled), links `approved_by`/`disbursed_by`/`rejected_by`/`cancelled_by` all to `users` |
| `loan_categories` | Loan type catalog (max amount, default interest, max tenure). | |
| `loan_repayments` | EMI schedule/ledger. | `status` enum(pending/paid/overdue/partial), `is_auto_deducted`, links to `monthly_payrolls` |

## Expense

| Table | Purpose | Key columns |
|---|---|---|
| `expenses` | Advance / settlement / reimbursement request. | `requirement_type` enum(advance/settlement/reimbursement), `status` enum(pending/approved/complete/cancelled). `amount` **decimal(15,2)** with **`CHECK (amount > 0)`** (`chk_expenses_amount_positive`, 2026-09-25). `paid_amount` decimal(15,2) default 0 (2026-09-26): cached `SUM(expense_payments.amount)`, rewritten from the payments table under the expense row lock by `ExpensePaymentService` on every payment write; used by `Expense::payable()` and `remaining_amount`; verified by `expense:reconcile-balances`. `date` is a real **DATE** (2026-09-26; was varchar) and still reads back as a `YYYY-MM-DD` string — deliberately **not** cast to Carbon because the mobile API serialises it. `expense_number` = `EXP-` + `LPAD(id, 6, '0')` set by `ExpenseNumberGenerator` (model `created` hook); the old `generate_expense_number` **DB trigger was dropped** (it overwrote app-set values, read `information_schema.AUTO_INCREMENT`, and was absent on a fresh `migrate`). **UNIQUE `(tenant_id, expense_number)`** (`expenses_tenant_number_unique`). `file` holds the receipt path: `expense/<tenantId>/<year>/<uuid>.<ext>` on the private `local` disk for new uploads; older rows hold public-relative paths of several shapes (`uploads/expense/file/…`, `expenses/2026/05/…`, `expenses/…`) until `php artisan expense:migrate-uploads` moves them. `is_direct_payment` boolean (2026-09-27): set on the auto-approved advance that a "direct payment" creates (replaces sniffing `description LIKE 'Direct payment:%'`; `project_id` is left NULL and the type is the tenant's first active expense type). **Phase 4 (2026-09-28):** `deleted_at` (**soft delete** — every normal query hides the row; `expense:purge-deleted` force-deletes after 90 days), `withdrawn_at` + `withdrawn_reason` varchar(500) (an employee's withdrawal; status is `cancelled`, `rejected_by` stays NULL), `parent_expense_id` (indexed — a reimbursement created from a settlement's shortfall points at that settlement; excluded from stats, reports and budget usage so it is not counted twice), `possible_duplicate_of` (id of an earlier look-alike claim; a flag, not a constraint). Migration `2026_09_28_000001_expense_workflow_completeness` is guarded/idempotent, and its `down()` refuses to run while soft-deleted rows exist. **Phase 5 (2026-09-29):** `payout_channel` varchar(10) NULL (`payroll` = the reimbursement is being paid through a payslip; NULL = voucher route — `Expense::payable()` excludes routed rows) and `payroll_target_month` char(7) NULL. |
| `expense_payroll_links` | **Phase 5 (2026-09-29)** — one row per attempt to pay a reimbursement through payroll (feature `expense_payroll_link`). | `status` enum(**queued** sent, not yet on a payslip / **linked** on `monthly_payroll_id` (a pending payslip) / **paid** the payslip was paid, `expense_payment_id` set / **released** given back to vouchers); `target_month`, `amount` (frozen at send time), `user_id`, `created_by`, `linked_at`/`paid_at`/`released_at`/`released_reason`. `monthly_payroll_id` FK → `monthly_payrolls` (`ON DELETE SET NULL`). **`active_expense_id`** VIRTUAL generated column = `expense_id` while the status is queued/linked/paid, else NULL, with UNIQUE `epl_one_active_per_expense` → at most one active link per expense (history rows accumulate). No FK on `expense_id` (module convention). |
| `expense_types` | Category catalog. | **Phase 4 (2026-09-28) claim policy**, all NULL = no rule: `max_amount` decimal(15,2), `receipt_required_above` decimal(15,2) (`0` = receipt always required), `max_backdate_days` unsigned smallint (not applied to advances). Enforced by `ExpensePolicyService`. |
| `expense_attachments` | Extra receipts of a claim (the first/primary receipt stays in `expenses.file`). Max 5 receipts per claim in total. | Rebuilt 2026-09-26 with a real PK/AUTO_INCREMENT + FKs (the live table had neither; 0 rows). Files on the private disk under `expense/<tenantId>/<year>/`; served by the signed `expense.attachment` route. |
| `expense_budgets` | Department/project/type spend caps per fiscal year (`2026-27`; a calendar-year company gets `2026`). NULL dimension = "any". `used_amount`/`remaining_amount` are convenience copies — the **live** usage is computed from `expenses` (approved/complete settlements + reimbursements, excluding `parent_expense_id` children and soft-deleted rows). | unique `(fiscal_year,department_id,project_id,expense_type_id)` — MySQL cannot see duplicates when a dimension is NULL, so `ExpenseBudgetController` checks that in PHP. **`enforcement`** enum(`warn`,`block`) default `warn` (Phase 4). FKs to departments/projects/expense_types (`ON DELETE SET NULL`). |
| `expense_payments` | Actual disbursement record against an expense. | `payment_mode` enum(cash/bank_transfer/cheque/upi); `idempotency_key` varchar(64) nullable **UNIQUE** (added 2026-09-25 — the payment form's per-modal key so a retry/double-click records once). Indexed 2026-09-26 on `expense_id`, `payment_date`, `reference_number`, `tenant_id` (the live table previously had only its PK, so every payment lookup was a full scan). No FKs (existing orphan rows). **Phase 5 (2026-09-29):** `payment_mode` enum gains **`payroll`** (also on `expense_payment_batches`) — set only by `ExpenseReimbursementPayrollService::settle`, never selectable in the voucher UI; such payments cannot be edited/voided from the voucher screens (reopen the payslip). **Phase 3 (2026-09-27):** `batch_id` (→ `expense_payment_batches`), `status` enum(**posted**/**voided**), `created_by`, `voided_by`, `voided_at`, `void_reason`; index `(expense_id, status)`. **Payments are never hard-deleted** — a mistake is voided (reversing ledger row + reason kept). Every "sum of payments" counts `status='posted'` only: `Expense::payments()` is posted-only, `Expense::allPayments()` includes voided. `paid_by` = payer (kept for compatibility), `created_by` = same at creation. |
| `expense_payment_batches` | **Payment voucher** (2026-09-27): one payment run covering one or many expenses; mode/date/reference entered once. A single-expense payment is a one-line voucher, so every payment belongs to one. Never deleted — a mistaken voucher is *voided*. | `voucher_number` (`PV-000001`, **UNIQUE per tenant**, gapless), `payment_date`, `payment_mode` enum(cash/bank_transfer/cheque/upi), `reference_number`, `bank_name`, `remarks`, `total_amount`, `line_count`, `status` enum(posted/voided), `idempotency_key` **UNIQUE** (retry/double-click = no-op), `created_by`, `voided_by/at`, `void_reason`. |
| `expense_voucher_sequences` | Per-tenant voucher counter (`tenant_id` PK, `last_number`). Incremented with an atomic upsert INSIDE the posting transaction, so the row lock serialises concurrent vouchers and a rolled-back posting returns its number (no gaps). | |
| `expense_status_histories` | Status-change audit trail. | Indexed 2026-09-26 on `expense_id`, `tenant_id` (was PK-only). No FKs. |
| `expense_transactions` | Wallet ledger feeding `user_expense_balances`. **Written only by `ExpenseLedgerService`.** Reversals are stored as NEGATIVE rows of the original type (`advance_reversed`/`reimbursement_reversed` types arrive with voids in Phase 3). | `transaction_type` enum(advance_credited/settlement_debited/reimbursement_paid), `balance_before`/`balance_after` |
| `user_expense_balances` | One row per employee (`UNIQUE user_id`, not per tenant). **Changed only by `ExpenseLedgerService`.** `current_balance = advance_balance − settlement_balance`; `reimbursement_balance` is a separate running total, not part of current. | 4 × decimal(15,2) |

**Migration note (Expense tables):** `2026_03_08_115000_create_expense_core_tables` codifies `expenses`, `expense_types`, `expense_transactions`, `user_expense_balances` (previously live-DB-only) and is dated *before* the March expense migrations so a fresh install works; on an existing DB every create is skipped. On the **shared dev DB the older March migrations (loans, expense_*, notifications, …) are still recorded as *pending***, because those tables were created outside Laravel — never run a bare `php artisan migrate` there; run the expense migrations explicitly with `--path=`. All Expense migrations are guarded (`hasTable`/`hasColumn`/`hasIndex`) and pre-check data, aborting with the offending ids instead of coercing it.

## Projects & Tasks

| Table | Purpose | Key columns |
|---|---|---|
| `projects` | | `status` enum(ongoing/pending/hold/completed/cancelled), `priority` enum(low/medium/high/critical), `budget` decimal(12,2) nullable, `progress_percentage`, `progress_manual_override` boolean (when true, `TaskProgressObserver` skips auto-recalculation so a PM-reported % sticks), `deadline_reminder_sent_at` (dedup for `projects:check-deadlines`) |
| `project_assigns` | Membership join table. | unique `(project_id,user_id)`, `is_head` |
| `project_updates` | Human-authored progress feed — completed/pending work, issues, next actions, notes; posting one with `reported_progress_percentage` sets that % and flips `progress_manual_override`. | `reported_progress_percentage` nullable |
| `project_comments` | Discussion thread on a project (mirrors `task_comments`). | soft-deletes |
| `project_attachments` | File uploads on a project (mirrors `task_attachments`). | `file_path`, `original_filename`, `mime_type`, `file_size` |
| `project_milestones` | Ordered milestone list, plotted on the detail page's timeline strip. | `status` enum(pending/completed), `sort_order` |
| `project_risks` | Risks and blockers (one table, `type` discriminator). | `type` enum(risk/blocker), `severity` enum(low/medium/high/critical), `status` enum(open/mitigated/resolved/closed) |
| `tasks` | Supports both individual and group tasks with configurable completion rules. | `task_mode` enum(individual/group), `group_completion_rule` enum(all_must_complete/any_one/percentage/lead_decides), `completion_type` enum(individual/collaborative/any_member), `priority` enum(low/medium/high/critical), `status` enum(pending/in_progress/completed/approved/rejected/cancelled), `extension_count` |
| `task_assigns` | Per-member assignment with **individual** progress tracking independent of the parent task. | `individual_status` enum(pending/in_progress/completed/blocked), `member_role` enum(lead/contributor/reviewer/observer) |
| `task_updates` | Deadline-extension requests/log. | `deadline_extension`, `old_deadline`/`new_deadline` |
| `task_approvals` | Completion approval workflow. | `status` enum(pending/approved/rejected/revision_requested) |
| `task_comments` / `task_attachments` | Discussion thread + files. | soft-deletes on comments |
| `daily_reports` | Daily work-log tied to a `requests` row (see WFH/Travel below), not to tasks directly. | `status` enum(DRAFT/SUBMITTED), unique `(request_id, report_date)` |

## Assets (2026-09-22)

Full asset lifecycle module: registration → assignment → employee acceptance → return/transfer/repair/damage → retirement/disposal, with a unified history table. Everything except the four "core" columns on `assets` is nullable by design — a tenant can register an asset with just a name, or fill in the full purchase/warranty/financial detail set. `assets`/`asset_categories`/`asset_types`/`vendors` are standalone tables with a required `tenant_id`; the rest are detail/child tables (nullable `tenant_id`, scoped via their `asset_id` FK — mirrors `project_attachments`/`project_milestones`).

| Table | Purpose | Key columns |
|---|---|---|
| `asset_categories` | Reference data (e.g. IT Equipment, Furniture, Vehicles). | `code` nullable (used as the asset-code prefix when set) |
| `asset_types` | Reference data, optionally under a category. | `asset_category_id` nullable |
| `vendors` | Supplier/vendor master used by both purchase and repair records. | |
| `assets` | The registration record — the only required fields are `asset_code` (system-generated, e.g. `AST-00001` or `{category-code}-00001`), `name`, and `status`. Everything else (classification, serial/model/brand, purchase, warranty, branch/location, condition, financial/depreciation) is nullable. | `status` enum(available/pending_acceptance/assigned/in_repair/damaged/lost/retired/disposed), `current_assignee_id` (denormalized — set only once an assignment is **accepted**, not on assign), `pre_repair_status` (internal — the status to restore to when a repair completes), soft-deletes |
| `asset_assignments` | One row per assignment episode; doubles as the current-holder pointer (latest row not `returned`/`transferred`) and full assignment history. | `status` enum(pending_acceptance/accepted/returned/transferred), `accepted_by` (usually = `user_id`, differs on admin force-accept), `return_condition` |
| `asset_transfers` | Transfer event record for reporting — the actual state change happens via `asset_assignments` (old row closed `transferred`, new row opened `pending_acceptance` for the new holder; acceptance is required again after a transfer). | `from_user_id`/`to_user_id`, `from_branch_id`/`to_branch_id` |
| `asset_repairs` | Repair/service record. Sending an assigned asset for repair does **not** end its assignment — `assets.status` flips to `in_repair` and restores from `pre_repair_status` on completion. | `status` enum(reported/in_progress/completed/cancelled) |
| `asset_damage_reports` | Damage/loss reports. | `type` enum(damaged/lost), `resolution` enum(written_off/repaired/replaced/recovered) |
| `asset_disposals` | Disposal record. Blocked while an asset is `assigned`/`pending_acceptance`/`in_repair` (must be returned/retired first). | `method` enum(sold/scrapped/donated/write_off/other) |
| `asset_histories` | Unified timeline behind the Asset History UI tab — one row per lifecycle transition, written alongside the tenant-wide `audit_logs` entry. | `action` (REGISTERED/ASSIGNED/ACCEPTED/ACCEPTANCE_FORCED/RETURNED/TRANSFERRED/SENT_FOR_REPAIR/REPAIR_COMPLETED/DAMAGED/LOST/RESOLVED/RETIRED/DISPOSED/UPDATED), `old_values`/`new_values` json |
| `asset_attachments` | File uploads (invoices, warranty cards, handover forms, damage photos) — mirrors `project_attachments`' shape with a `context` label instead of polymorphism. | `context` nullable string label |

## Recruitment / ATS

| Table | Purpose | Key columns |
|---|---|---|
| `candidates` | Candidate pool (soft-deletes). | `status` enum(new/contacted/screening/interviewing/offered/onboarded/rejected/hired), `source` enum(career_page/linkedin/naukri/indeed/referral/agency/walkin/other) |
| `candidate_documents` | Resume/ID/offer letter uploads with verification flag. | `document_type` enum (11 values) |
| `job_openings` | Requisitions (soft-deletes). | `status` enum(draft/published/closed/on_hold), `close_reason` (added 2026-09-18 — previously the close reason was accepted by the UI and silently discarded) |
| `job_applications` | Candidate ↔ opening link. | unique `(job_opening_id, candidate_id)`, `current_stage` |
| `recruitment_stages` | Tenant-configurable pipeline stages. | `stage_type` enum(screening/technical/hr/managerial/assignment/final) |
| `recruitment_workflow_logs` | Stage-transition audit log. | `from_stage`/`to_stage`, `metadata` JSON |
| `interviews` | Scheduling + outcome. | `interview_type` enum(online/offline/telephonic/video), `status` enum(scheduled/completed/cancelled/rescheduled/no_show), `outcome` enum(selected/rejected/next_round/on_hold), self-referencing `rescheduled_from` |
| `interview_feedbacks` | Per-interviewer scorecard. | unique `(interview_id, interviewer_id)`, 1–5 ratings on 5 dimensions, `recommendation` enum(strong_hire/hire/maybe/no_hire) |
| `job_offers` | Offer letter + comp details. | `offer_status` enum(draft/sent/accepted/rejected/expired/withdrawn), `employment_type` enum(full_time/part_time/contract/internship/temporary — `temporary` added 2026-09-18 to match `job_openings.employment_type`, which already had it), full comp breakdown (basic/hra/other_allowances/variable_pay) |
| `email_templates` / `email_logs` | **Dead tables** — schema exists (`template_type`/`email_type` share the same enum of 7 email kinds) but no model or code path writes to either; real recruitment emails go through `App\Mail\*` Mailables instead. Not part of the live pipeline — don't build against them without adding the missing `EmailTemplate`/`EmailLog` models first. |
| `career` | **Does not exist in the live DB** (confirmed via `SHOW CREATE TABLE`, 2026-09-18) despite this row's earlier claim — the entire `CareerController`/`Career`-model duplicate candidate-intake pipeline it backed was dead/unrouted code and has been deleted. The real public application intake is `RecruitmentController`/`careers.*` routes → `candidates`/`job_applications`. |

## Onboarding / Offboarding

| Table | Purpose | Key columns |
|---|---|---|
| `onboarding_assignments` | Links an accepted offer to an onboarding checklist run; produces the eventual `users` row via `App\Services\Recruitment\EmployeeProvisioningService::hire()` (implemented 2026-09-18 — previously this table had no model at all). | unique on both `candidate_id` and `job_offer_id`, `onboarding_status` enum(not_started/in_progress/completed/cancelled) |
| `onboarding_tasks` | Checklist template item catalog. Rows with `tenant_id = NULL` are the shared/global default catalog (9 seeded rows spanning all 6 real categories) visible to every tenant — see `OnboardingTask::scopeForTenant()`. | `task_category` enum(document/account_creation/asset/training/compliance/orientation/other) |
| `onboarding_task_items` | Per-assignment instance of a checklist item, generated from `onboarding_tasks` when `OnboardingService::startOnboarding()` runs (on offer acceptance). | `status` enum(pending/in_progress/completed/skipped/overdue) |
| `offboarding_requests` | Rebuilt 2026-09-30 (migrations now exist — `2026_09_30_000001`/`000002`). Full exit workflow: approval (via `approval_requests`, not this table) → knowledge transfer → clearance → exit interview → final settlement. `manager_review_status`/`hr_review_status`/their `*_by`/`*_at`/`*_comments` columns are now **denormalized mirrors only** (written by `OffboardingApprovalHandler`, never the source of truth) — the real approval trail lives in `approval_requests`/`approval_actions`. New columns: `current_stage` (single column driving the UI stepper), `notice_period_days_required`/`original_last_working_date` (snapshotted at submission so a later tenant-setting change doesn't retroactively affect an in-flight request), `settlement_computed_total`/`settlement_final_total`/`settlement_finalized_*`/`settlement_payment_reference`, `exit_interview_skipped`/`exit_interview_skip_reason`, `rejected_*`/`cancelled_*` (previously overloaded into `feedback`/review-comment columns). The `update_offboarding_status` BEFORE UPDATE trigger that used to derive `status` was dropped (`2026_09_30_000004`) — `App\Services\Offboarding\OffboardingService` is now the sole writer. The `generate_offboarding_request_code` BEFORE INSERT trigger (fills `request_code`) is unrelated and still live. | multiple parallel `*_status` enum columns per sub-workflow, `full_final_settlement`, FK-enforced `employee_id`/`created_by`/`approved_by`/etc. -> `users.id` |
| `exit_interviews` | Structured exit interview responses (5 rating dimensions + free text). `status` column added `2026_09_30_000003` (previously missing entirely — the controller wrote `status` on every save but it was silently dropped, both by the missing column and by `$fillable`). | |
| `offboarding_clearance_templates` | Per-tenant configurable clearance checklist catalog (`2026_09_30_000005`). `tenant_id = NULL` rows are the shared global default set — same pattern as `onboarding_tasks`. | `category` enum(it/admin/finance/hr/other), `applies_to_reasons` json (null = all reasons) |
| `offboarding_clearance_tasks` | Per-request instantiated clearance checklist state, seeded by `OffboardingService::startClearance()` from templates plus one row per the employee's real open `asset_assignments` row. Completing an `asset`-sourced row calls `AssetLifecycleService::returnAsset()` — the real assignment updates too. | `source` enum(checklist/asset), FK `asset_assignment_id` nullable -> `asset_assignments.id` |
| `offboarding_settlement_items` | The final-settlement worksheet (`2026_09_30_000006`), one row per line (pending salary, leave encashment, loan deduction, expense advance/reimbursement, notice shortfall, severance, custom). `computed_amount` is system-calculated and never overwritten; `override_amount` is an HR/Finance edit; `final_amount` = override ?? computed. | `line_type` enum, `source_type`/`source_id` point at the real `Loan`/`LeaveBalance`/etc. row |
| `offboarding_notice_overrides` | Audit trail of every notice-period deviation (`2026_09_30_000007`) — waiver / early_release / extension, each with reason + requester + approver. Approving one is what mutates `offboarding_requests.last_working_date`; a request can accumulate several over its life, hence a child table rather than flat columns. | `type` enum(waiver/early_release/extension), `status` enum(pending/approved/rejected) |

## Performance

Redesigned 2026-09-24 around **daily** scoring: `employee_daily_performance` is
now the fact table; `employee_kpi_scores` is a **monthly rollup** of it
(computed by `App\Services\Performance\PerformanceRollupService`, never
calculated independently day-by-day the way the old, now-deleted
`PerformanceCalculationService` did). Weekly figures are always computed
on-demand from `employee_daily_performance` (no weekly table — same choice as
`attendances`/`attendance_summaries` having no weekly table).

| Table | Purpose | Key columns |
|---|---|---|
| `employee_daily_performance` | One row per employee per calendar day — the performance "fact" table. Component score columns (`attendance_score`, `task_completion_score`, `task_ontime_score`, `project_participation_score`, `regularization_score`, `overall_daily_score`) are **nullable**: null means genuinely inapplicable that day (holiday, no tasks assigned, no regularization request, etc.) and is excluded from every average — never confused with an earned 0. | unique `(tenant_id,user_id,performance_date)`, `day_type` enum-like string (working/weekoff/holiday/full_leave_paid/full_leave_unpaid/half_leave), `attendance_status` (mirrors `AttendanceDayResolver`'s token vocabulary), `calculation_status` (pending/calculated/excluded/failed), `is_late`/`is_early_departure`/`is_unauthorized_absent` booleans, `regularization_status`, task + project-scoped task counts, `components_included`/`calculation_audit` JSON |
| `performance_policies` | Tenant-configurable, versioned scoring weights and penalty thresholds — mirrors `attendance_policies`' exact shape (nullable `tenant_id` = global default, `effective_from` versioning, soft-deletes). Resolved via `App\Services\Performance\PerformancePolicyResolver`. | 6 top-level weight columns (attendance/task_completion/task_ontime/project_participation/regularization/manager_rating, sum to 100), late/early-departure grace+penalty+cap, regularization penalty split by **approved/rejected/pending** outcome, task-overdue penalty, unique `(tenant_id, effective_from)` |
| `employee_kpi_scores` | Monthly rollup of `employee_daily_performance` (+ the manager rating, blended in via the same canonical formula). `attendance_score`/`task_completion_score`/`deadline_met_score`/`regularization_score` were made **nullable** in this redesign (were `NOT NULL DEFAULT 0.00` — silently conflating "no data this month" with "scored zero"). Added `project_participation_score` + counts, `overdue_tasks`, `days_calculated`/`days_expected` (rollup completeness audit), `policy_effective_from`, `manager_rating_included`, `weights_snapshot`. | unique `(user_id, reporting_month)` and `(tenant_id,user_id,reporting_month)`, `status` enum(draft/calculated/reviewed/approved/adjusted), several JSON detail columns (`attendance_details`, `task_details`, `calculation_audit`…) |
| `manager_performance_reviews` | Manager's qualitative monthly review, optionally linked to a `employee_kpi_scores` row. | `overall_rating` (1–5), `status` enum(draft/submitted/acknowledged) |

## Meetings / MoM

| Table | Purpose | Key columns |
|---|---|---|
| `meetings` | Supports recurrence (`parent_meeting_id` self-reference), MoM (minutes) lifecycle, and a **generated column** `duration_minutes` computed from start/end time. | `meeting_type` enum(physical/virtual/hybrid), `mom_status` enum(not_started/draft/finalized), `agenda_items`/`decisions` JSON, `recurrence_pattern` |
| `meeting_participants` | Attendance + role tracking, including who is the MoM writer. | unique `(meeting_id,user_id)`, `role` enum(organizer/presenter/attendee/optional), `attendance_status` enum (7 values incl. tentative/late) |
| `meeting_histories` | Change/action audit log for a meeting. | `old_values`/`new_values` JSON |

## Approvals (generic workflow engine)

Used by leave/regularization/overtime/payroll-structure changes and, as of 2026-09-30, offboarding via `subject_type`/`subject_id` polymorphism, not FK'd to any one domain table. Expense and the Requests/WFH module remain bespoke single-approver flows, not on this engine (see `docs/modules.md`).

| Table | Purpose | Key columns |
|---|---|---|
| `approval_workflows` | Named workflow per `request_type` (e.g. leave, expense) per tenant. | `applies_to` JSON (scoping rules) |
| `approval_workflow_steps` | Ordered approval levels within a workflow. | unique `(workflow_id, level)`, `approver_type`, `quorum` enum(any/…), `sla_hours`, `on_breach` |
| `approval_requests` | An instance of something awaiting approval. | polymorphic `subject_type`+`subject_id`, `current_level`, `status` |
| `approval_actions` | Individual approve/reject actions against a request. | `level`, `action`, `meta` JSON |
| `approval_delegations` | "Out of office" delegate-my-approvals config. | `request_types` JSON, `starts_on`/`ends_on` |

## Announcements / Requests (WFH, Travel) / Misc

| Table | Purpose | Key columns |
|---|---|---|
| `announcements` | Company-wide announcements, optional acknowledgment requirement + expiry. | `acknowledge`, `expire_date` |
| `announcement_acknowledgments` | Per-user ack tracking: `tenant_id`, `announcement_id`, `user_id`, `acknowledged_at`; unique (`announcement_id`,`user_id`). | FK `announcement_id`→`announcements` (cascade), `user_id`→`users` (cascade). Table created on hrm_22_04 2026-09-30 (migration had never been run). |
| `requests` | Generic WFH/Travel request (feeds `daily_reports` for WFH work logs). | `status` enum(PENDING/APPROVED/REJECTED/CANCELLED, **uppercase** — inconsistent with most other status enums which are lowercase) |
| `request_types` | Only 2 values today. | `type_name` enum(WFH/TRAVEL) |
| `request_attachments` / `request_histories` | Files + status-change audit trail. | `request_histories.action` enum(CREATED/SUBMITTED/APPROVED/REJECTED/CANCELLED/UPDATED) |
| `otps` | SMS OTP login codes (Airtel SMS gateway). | `expire_at`, `is_used` |
| `notifications` | Laravel's standard notifications table (FCM push + in-app). | polymorphic `notifiable_type`/`notifiable_id` |
| `broadcast_notifications` | Broadcast Notification module (2026-09-25) — the broadcast definition. Shared with `hrm-superadmin` (plain unscoped model there, no migration on that side). **No `tenant_id` column** — deliberately provenance-only via `origin_tenant_id` (nullable), not a scoping boundary; see `broadcast_recipients` below and `docs/modules.md`'s Broadcast Notifications section for why. | `origin` enum(tenant_admin/superadmin), `audience_type` enum, `audience_filters` JSON, `status` enum(draft/scheduled/sending/sent/failed/cancelled/expired) |
| `broadcast_recipients` | Broadcast Notification module — per-recipient snapshot, **the real tenant-scoping boundary** for the module (`tenant_id` always populated, never null; `recipient_type=super_admin` rows store the sentinel `tenant_id=0`, since a super admin isn't tenant data — no query ever scopes a super-admin-recipient lookup by `tenant_id`, always `recipient_type='super_admin' AND super_admin_id=X` instead, so the sentinel is inert). `UNIQUE(broadcast_id,user_id)` + `UNIQUE(broadcast_id,super_admin_id)` prevent duplicate recipient rows; `notification_id` links to the matching dual-written `notifications` row **for `recipient_type=tenant_user` rows only** — `super_admin` rows never get a `notifications` row (this panel's own bell reads `broadcast_recipients` directly instead), so `notification_id` stays NULL for them by design. Written directly by both this app and `hrm-superadmin` (no HTTP bridge — see `docs/modules.md`'s Phase C section for the direct-write delivery path and the queue-name-collision bug that was caught and fixed there). No FK on `user_id`/`super_admin_id` (a later user deletion must not corrupt broadcast history). | `tenant_id` (NOT NULL, no FK, indexed), `recipient_type` enum(tenant_user/super_admin), `read_at`/`delivered_at`/`action_clicked_at` |
| `locations` | Generic lat/long store — minimal, purpose unclear from schema alone; check usages before relying on it. | |

## Laravel infra tables (not app data)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens` (Sanctum — see [[hrm-api-auth-jwt]] re: actual usage vs JWT), `migrations`.

---

## Notable indexes worth knowing about

- **`attendances`**: composite `(tenant_id,user_id,date)` and `(tenant_id,user_id,clock_in_utc)` — the two indexes basically every attendance query pattern should hit.
- **`attendance_tracks`**: composite `(attendance_id,track_time)` and `(tenant_id,user_id,track_time)`, added 2026-09-13 specifically to handle GPS breadcrumb scale (see [[hrm-field-tracking]]) — legacy/frozen as of 2026-10-01.
- **`attendance_tracking_sessions`**/**`attendance_tracking_points`**: tenant-first composite `(tenant_id,user_id,<time_column>)` for range scans, plus a nullable-unique `(tenant_id,session_id,point_id)` on points for retry-safe dedup — same idiom as `attendance_punches.client_ref`.
- **`employee_kpi_scores`**: heavily indexed (13 secondary indexes) including a composite `(tenant_id,reporting_month,overall_score)` for leaderboard-style queries.
- **`employee_daily_performance`**: `(tenant_id,performance_date)` and `(user_id,performance_date)` for the rollup's range scans, `(tenant_id,calculation_status)` for finding pending/failed rows.
- **`loans`** / **`loan_repayments`**: indexed on `(tenant_id,status)`, `(status,due_date)`, `(user_id,status,remaining_amount)` — built for dashboards showing "who owes what."
- **`biometric_punches`**: dedupe-safety unique key `(serial_number,enroll_no,punched_at,raw_verify_mode)` prevents double-ingesting the same device punch.
- **`attendance_punches`**: composite `(tenant_id,user_id,date,punched_at)` for pairing/aggregation, `(tenant_id,status)` and `(attendance_id)` for lookups; `(tenant_id,user_id,client_ref)` unique for mobile offline-retry idempotency (nullable, so non-retried punches are unaffected).
- **`meetings.duration_minutes`** is a **generated/virtual column** (`TIMESTAMPDIFF` of date+start/end time) — don't try to write to it directly.

## Notable queries

Most of the codebase uses Eloquent query builder rather than raw SQL (only 3 files use `DB::raw` at all). The one interesting pattern is single-pass aggregate rollups in `app/Services/Analytics/AttendanceAnalyticsService.php`:

```php
// app/Services/Analytics/AttendanceAnalyticsService.php (~line 55-64)
->select(
    DB::raw('COUNT(*) as rows_count'),
    DB::raw("SUM(CASE WHEN effective_status IN ('present','late','overtime','early_departure') OR attendance_status IN ('present','late','overtime') THEN 1 ELSE 0 END) as present"),
    DB::raw("SUM(CASE WHEN effective_status='half_day' OR attendance_status='half_day' THEN 1 ELSE 0 END) as half_day"),
    DB::raw("SUM(CASE WHEN attendance_status IN ('on_leave','first_half_leave','second_half_leave') THEN 1 ELSE 0 END) as on_leave"),
    DB::raw('SUM(COALESCE(late_minutes,0)) as late_minutes'),
    DB::raw('SUM(COALESCE(worked_hours,0)) as worked_hours')
)
```
Note it reads **both** `effective_status` and the legacy `attendance_status` column — a sign the two-column migration (`2026_08_30_000003_add_effective_status_to_attendances.php`) is mid-rollout; new attendance logic should prefer `effective_status` but must stay compatible with rows that only have `attendance_status` set.

`app/Services/Analytics/AnomalyScanner.php` groups `attendance_logs` by `user_id` with `COUNT(*)` to flag high-frequency-event anomalies feeding `attendance_anomalies`.

`app/Services/Attendance/PeriodLockService.php` uses `DB::raw('COALESCE(created_at, NOW())')` when backfilling lock records.
