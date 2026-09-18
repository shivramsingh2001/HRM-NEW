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
| `tenants` | One row per customer org (the actual "tenant"). | `uuid`, `subdomain`/`custom_domain`, `status` enum(active/inactive/suspended/trial), `subscription_plan_id`, `max_employees`, `settings` JSON, `field_tracking_*`, `payroll_dynamic_ui_enabled`, `late_halfday_enabled`, `default_weekoff_days` JSON |
| `companies` | Legal company record(s) under a tenant (a tenant can theoretically have >1 company; `isdefault` flag). | `tenant_id`, `subdomain`, `gstnumber`/`pannumber`, `settings` JSON |
| `subscription_plans` | Plan catalog (pricing/limits/features) super-admin manages. | `pricing_type` enum(fixed/per_employee_per_day/per_employee_per_month), `features` JSON, `max_employees` |
| `tenant_subscriptions` | A tenant's active/historic plan assignment. | `plan_id`, `features_snapshot` JSON, `status` enum(active/trial/expired/cancelled) |
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
| `company_registrations` | Self-serve signup intake (pre-tenant). | (no migration for schema detail beyond base; check model) |

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
| `users` | Core auth/identity row. Split-profile pattern — most HR data lives in satellite tables below. | `role` (string) + `role_id` (FK to `roles`, newer RBAC layered on top of the legacy string role), `employee_id`, `status`, `fcm_tokens` JSON, `card_number` (biometric card) |
| `user_basic_details` | Personal info: DOB, gender, blood group, statutory numbers (Aadhaar/PAN/UAN/PF/ESIC). Also has 4 **deprecated** legacy document columns (`experience_letter`, `tenth_marksheet`, `twelfth_marksheet`, `highest_qualification_certificate`) kept read-only for one release cycle — new uploads go to `employee_documents` instead. | 1:1 with `users` |
| `user_job_details` | Employment info: designation/department/office_branch, `payroll_master_id`, `attendance_type` (manual_attendance / face_verification), **`location_tracking_enabled`** (field-tracking seat flag), `type` enum(field/office). `reporting_head` is the **primary** reporting head only (denormalized, kept in sync from `user_reporting_heads` on every employee-wizard save) — for the full multi-head set, use `user_reporting_heads` / `User::reportingHeads()` / `User::scopeManagedBy()`. **`office_branch`** (FK-by-convention to `attendance_locations.id`, `0` = "any location") is for attendance geofencing only; **`branch_id`** (2026-09-20, nullable, no FK) is the separate organizational Branch membership — see `attendance_locations`/`company_branches` below. | 1:1 with `users` |
| `user_bank_details` | Bank account + statutory numbers (UAN/PF/ESI). | 1:1 with `users` |
| `user_reporting_heads` | Multi reporting-head pivot (2026-09-19): `user_id`, `reporting_head_id`, `is_primary`. Source of truth for "who manages whom"; `user_job_details.reporting_head` is a denormalized copy of the primary row for backward compatibility with older single-head read sites. | unique `(user_id, reporting_head_id)`, indexed on `reporting_head_id` for reverse ("my team") lookups |
| `employee_documents` | Dynamic, multi-row employee document uploads (2026-09-19) — replaces the 4 fixed columns on `user_basic_details`. `document_type` is a predefined slug (`App\Models\EmployeeDocument::$documentTypes`) or `other` (+ free-text `document_type_other`), optional `document_name`, `file_path`, soft-deletable. | indexed on `user_id` |
| `user_locations` | Address + geo (country/state/city FK to master tables, lat/long). | |
| `user_shifts` | Per-day shift assignment (date-specific override of default shift). | unique `(user_id, date)` |
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
| `attendances` | One row per employee per day — the attendance "fact" table. | `attendance_status` enum(present/absent/half_day/late/early_departure/overtime/on_leave/first_half_leave/second_half_leave/holiday/weekoff), `attendance_type` enum(manual/fingerprint/face/card/app), `location_verification` enum(verified/unverified/out_of_bounds), `clock_in_utc`/`clock_out_utc` + `tz`, `day_fraction`, `metadata` JSON. Composite indexes `(tenant_id,user_id,date)` and `(tenant_id,user_id,clock_in_utc)`. |
| `attendance_logs` | Immutable event log feeding `attendances` (check-in/out, geofence enter/exit, manual adjustment…). High-volume GPS/device metadata table. | `event_type` enum (9 values incl. `geofence_enter/exit`, `device_change`), lat/long/accuracy/speed/bearing, `is_mock_location`, `verification_method`, `before`/`after` JSON diff |
| `attendance_tracks` | GPS breadcrumb trail while clocked in (field employees). High write volume. | `lat`/`long` (7-decimal precision), composite indexes `(attendance_id,track_time)` and `(tenant_id,user_id,track_time)` — added 2026-09-13 for scale |
| `attendance_summaries` | Pre-aggregated monthly rollup per user (dashboard/report source of truth, avoids re-scanning `attendances`). | unique `(user_id, year_month)`, `stale_at` (invalidation marker), dozens of aggregate counters |
| `attendance_regularizations` | Employee request to fix a missed/incorrect punch. | `status` enum(pending/approved/rejected) |
| `attendance_anomalies` | Auto-detected suspicious attendance events (see `AnomalyScanner` service). | `type`, `severity` enum(→ low/medium/high implied), `status` enum(open/…), dedupe via unique `(tenant_id, fingerprint)` |
| `attendance_policies` | Tenant-configurable policy: present/half-day thresholds, grace/rounding minutes, OT multiplier, sandwich-leave rule. Versioned by `effective_from`. | `present_ratio`, `half_day_ratio`, `grace_minutes`, `overtime_after_hours`, `sandwich_leave` |
| `attendance_period_locks` | Locks a tenant+month from further attendance edits (pre-payroll lock). | unique `(tenant_id, year_month)`, `status` enum(open/…) |
| `shifts` | Shift master (start/end time, grace, break). | `color_code` for UI |
| `overtime_requests` / `overtime_settings` | Employee OT request + tenant OT policy (rate multiplier, auto-approve threshold). | |
| `biometric_devices` | Registered SBXPC biometric terminals per tenant. | `serial_number` (unique), `direction_mode`, `auto_provision`, `provision_scope` |
| `biometric_enrollments` | Maps a device's local enrollment slot to a platform `user_id`. | unique `(biometric_device_id, enroll_no)`, `sync_state` |
| `biometric_punches` | Raw punch events ingested from devices before being folded into `attendances`. | dedupe unique `(serial_number, enroll_no, punched_at, raw_verify_mode)`, `status` enum(pending/…) |

> Old CAMS fingerprint pipeline (`fingerprint_devices`, `fingerprint_punch_logs`, `device_user_maps`) was **dropped** on 2026-09-14 (`2026_09_14_000001_drop_legacy_fingerprint_tables.php`) and replaced by the `biometric_*` tables above — see [[hrm-biometric-integration]] memory.

## Field Tracking (GPS add-on)

| Table | Purpose | Key columns |
|---|---|---|
| `field_tracking_usage` | Monthly seat-usage rollup per tenant for billing (peak/avg seats used, GPS row volume). | unique `(tenant_id, year_month)`, `enabled_user_ids` JSON |

Per-employee toggle lives on `user_job_details.location_tracking_enabled`; tenant-level config (`field_tracking_enabled`, `field_tracking_seats`, `field_tracking_ping_seconds`, `field_tracking_retention_days`) lives on `tenants`.

## Leave & Holidays

| Table | Purpose | Key columns |
|---|---|---|
| `leaves` | Leave applications. | `status` enum(pending/approved/cancelled), `start_session`/`end_session` enum(session1/session2/fullday) for half-day leave, `source` (self/admin-applied), `deduct_balance` |
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
| `monthly_payrolls` | The actual monthly payroll run output per employee — huge flat table with every earning/deduction as its own column, `payment_status` enum(pending/processed/paid/cancelled), links to `payroll_runs`/`user_payrolls`. |
| `payroll_components` | Free-form extra earning/deduction line items attached to a `monthly_payrolls` row. |

**New component-based engine** (built 2026-09-12, tenant opt-in via `tenants.payroll_dynamic_ui_enabled`):
| Table | Purpose |
|---|---|
| `payroll_component_master` | Tenant-defined pay components (earning/deduction/employer_contribution/reimbursement) with formula config: `calculation_method` (fixed/percentage), `calculation_base` (basic/gross_pass1/ctc), wage ceilings, proration rule. |
| `payroll_component_templates` | Platform-level starter templates offered to new tenants (not tenant-scoped). |
| `payroll_structures` | Named salary structure (e.g. "Standard CTC") combining components. |
| `payroll_structure_components` | Join table: which components belong to a structure, with per-structure overrides. |
| `payroll_employee_structures` | Employee's assigned structure, versioned/effective-dated, with approval workflow (`status` enum draft→pending_approval→active→superseded). |
| `payroll_employee_components` | Per-employee component overrides layered on their structure. |
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
| `expenses` | Advance / settlement / reimbursement request. | `requirement_type` enum(advance/settlement/reimbursement), `status` enum(pending/approved/complete/cancelled) |
| `expense_types` | Category catalog. | |
| `expense_attachments` | Receipt uploads. | |
| `expense_budgets` | Department/project/type budget caps per fiscal year, with running `used_amount`/`remaining_amount`. | unique `(fiscal_year,department_id,project_id,expense_type_id)` |
| `expense_payments` | Actual disbursement record against an expense. | `payment_mode` enum(cash/bank_transfer/cheque/upi) |
| `expense_status_histories` | Status-change audit trail. | |
| `expense_transactions` | Wallet ledger feeding `user_expense_balances`. | `transaction_type` enum(advance_credited/settlement_debited/reimbursement_paid), `balance_before`/`balance_after` |

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

## Recruitment / ATS

| Table | Purpose | Key columns |
|---|---|---|
| `candidates` | Candidate pool (soft-deletes). | `status` enum(new/contacted/screening/interviewing/offered/onboarded/rejected/hired), `source` enum(career_page/linkedin/naukri/indeed/referral/agency/walkin/other) |
| `candidate_documents` | Resume/ID/offer letter uploads with verification flag. | `document_type` enum (11 values) |
| `job_openings` | Requisitions (soft-deletes). | `status` enum(draft/published/closed/on_hold) |
| `job_applications` | Candidate ↔ opening link. | unique `(job_opening_id, candidate_id)`, `current_stage` |
| `recruitment_stages` | Tenant-configurable pipeline stages. | `stage_type` enum(screening/technical/hr/managerial/assignment/final) |
| `recruitment_workflow_logs` | Stage-transition audit log. | `from_stage`/`to_stage`, `metadata` JSON |
| `interviews` | Scheduling + outcome. | `interview_type` enum(online/offline/telephonic/video), `status` enum(scheduled/completed/cancelled/rescheduled/no_show), `outcome` enum(selected/rejected/next_round/on_hold), self-referencing `rescheduled_from` |
| `interview_feedbacks` | Per-interviewer scorecard. | unique `(interview_id, interviewer_id)`, 1–5 ratings on 5 dimensions, `recommendation` enum(strong_hire/hire/maybe/no_hire) |
| `job_offers` | Offer letter + comp details. | `offer_status` enum(draft/sent/accepted/rejected/expired/withdrawn), full comp breakdown (basic/hra/other_allowances/variable_pay) |
| `email_templates` / `email_logs` | Recruitment email templating + send log. | `template_type`/`email_type` share the same enum of 7 email kinds |
| `career` | Public careers-page content (no detailed columns captured — check model/migration if touched). | |

## Onboarding / Offboarding

| Table | Purpose | Key columns |
|---|---|---|
| `onboarding_assignments` | Links an accepted offer to an onboarding checklist run; produces the eventual `users` row. | unique on both `candidate_id` and `job_offer_id`, `onboarding_status` enum(not_started/in_progress/completed/cancelled) |
| `onboarding_tasks` | Checklist template item catalog. | `task_category` enum(document/account_creation/asset/training/compliance/orientation/other) |
| `onboarding_task_items` | Per-assignment instance of a checklist item. | `status` enum(pending/in_progress/completed/skipped/overdue) |
| `offboarding_requests` | Very wide table covering the full exit workflow: manager review → HR review → asset/document/knowledge-transfer clearance → exit interview → final settlement. | multiple parallel `*_status` enum columns per sub-workflow, `full_final_settlement` |
| `exit_interviews` | Structured exit interview responses (5 rating dimensions + free text). | |

## Performance

| Table | Purpose | Key columns |
|---|---|---|
| `employee_kpi_scores` | Monthly computed performance score combining attendance/task/deadline/regularization/manager-rating, weighted into `overall_score` + letter `grade`. Very wide table with full calculation audit trail. | unique `(user_id, reporting_month)` and `(tenant_id,user_id,reporting_month)`, `status` enum(draft/calculated/reviewed/approved/adjusted), several JSON detail columns (`attendance_details`, `task_details`, `calculation_audit`…) |
| `manager_performance_reviews` | Manager's qualitative monthly review, optionally linked to a `employee_kpi_scores` row. | `overall_rating` (1–5), `status` enum(draft/submitted/acknowledged) |

## Meetings / MoM

| Table | Purpose | Key columns |
|---|---|---|
| `meetings` | Supports recurrence (`parent_meeting_id` self-reference), MoM (minutes) lifecycle, and a **generated column** `duration_minutes` computed from start/end time. | `meeting_type` enum(physical/virtual/hybrid), `mom_status` enum(not_started/draft/finalized), `agenda_items`/`decisions` JSON, `recurrence_pattern` |
| `meeting_participants` | Attendance + role tracking, including who is the MoM writer. | unique `(meeting_id,user_id)`, `role` enum(organizer/presenter/attendee/optional), `attendance_status` enum (7 values incl. tentative/late) |
| `meeting_histories` | Change/action audit log for a meeting. | `old_values`/`new_values` JSON |

## Approvals (generic workflow engine)

Used by leave/expense/loan/offboarding/payroll-structure changes etc. via `subject_type`/`subject_id` polymorphism, not FK'd to any one domain table.

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
| `announcement_acknowledgments` | Per-user ack tracking (added 2026-09-12; no detailed columns captured beyond migration name — check model if needed). | |
| `requests` | Generic WFH/Travel request (feeds `daily_reports` for WFH work logs). | `status` enum(PENDING/APPROVED/REJECTED/CANCELLED, **uppercase** — inconsistent with most other status enums which are lowercase) |
| `request_types` | Only 2 values today. | `type_name` enum(WFH/TRAVEL) |
| `request_attachments` / `request_histories` | Files + status-change audit trail. | `request_histories.action` enum(CREATED/SUBMITTED/APPROVED/REJECTED/CANCELLED/UPDATED) |
| `otps` | SMS OTP login codes (Airtel SMS gateway). | `expire_at`, `is_used` |
| `notifications` | Laravel's standard notifications table (FCM push + in-app). | polymorphic `notifiable_type`/`notifiable_id` |
| `locations` | Generic lat/long store — minimal, purpose unclear from schema alone; check usages before relying on it. | |

## Laravel infra tables (not app data)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens` (Sanctum — see [[hrm-api-auth-jwt]] re: actual usage vs JWT), `migrations`.

---

## Notable indexes worth knowing about

- **`attendances`**: composite `(tenant_id,user_id,date)` and `(tenant_id,user_id,clock_in_utc)` — the two indexes basically every attendance query pattern should hit.
- **`attendance_tracks`**: composite `(attendance_id,track_time)` and `(tenant_id,user_id,track_time)`, added 2026-09-13 specifically to handle GPS breadcrumb scale (see [[hrm-field-tracking]]).
- **`employee_kpi_scores`**: heavily indexed (13 secondary indexes) including a composite `(tenant_id,reporting_month,overall_score)` for leaderboard-style queries.
- **`loans`** / **`loan_repayments`**: indexed on `(tenant_id,status)`, `(status,due_date)`, `(user_id,status,remaining_amount)` — built for dashboards showing "who owes what."
- **`biometric_punches`**: dedupe-safety unique key `(serial_number,enroll_no,punched_at,raw_verify_mode)` prevents double-ingesting the same device punch.
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
