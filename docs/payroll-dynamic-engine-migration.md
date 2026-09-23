# Payroll Dynamic Engine — Migration & Cutover Guide

This is the instructions file the Payroll Audit's Phase 2 pass produced, covering how to move a
tenant from the legacy flat payroll engine onto the dynamic component-catalog engine
(`App\Services\Payroll\PayrollCalculationEngine`) — including guidance for doing this safely in
**production, where 10+ companies already have real, already-generated payroll history** that
this documentation set has no direct access to.

**2026-09-18 update — legacy engine screens removed from this codebase.** Per the user's request
("I want only dynamic payroll, not both"), `PayrollMasterController`/`UserPayrollController` and
their routes/views/nav links are deleted; see `docs/modules.md`'s Payroll section for the full
list of code changes. **This makes the cutover sequence below a hard prerequisite for any
still-legacy production tenant, not just a recommendation** — once this code ships, a tenant with
`payroll_dynamic_ui_enabled=0` has no UI left to create/edit a payroll master or assign an
employee's salary. Run the full sequence for every remaining production tenant, with a DB backup,
before deploying this change to production. `payroll_masters`/`user_payrolls` themselves are kept
in the DB as read-only history — this update does not touch or drop them.

All local dev tenants have been cut over except one: **tenant 10 ("Pankh") failed its engine-diff
parity check** (one employee's already-paid historical payslip doesn't reconcile — see
`docs/modules.md`'s Payroll section for the specifics) and was deliberately left on the legacy
engine rather than forced through. Expect the same kind of real, tenant-specific reconciliation
question to come up for at least some of the 10+ production tenants — budget time for it, and
resolve each one on its own merits rather than overriding `--tolerance` to make the tool pass.

## What actually changes on cutover, and what never does

Cutover is controlled by one column: `tenants.payroll_dynamic_ui_enabled`. Once it's `1` for a
tenant:

- **Salary input** (`UserController`'s employee wizard/profile payroll step) stops writing
  `user_payrolls` entirely and writes real `payroll_employee_structures` +
  `payroll_employee_components` rows instead (`PayrollStructureAssignmentService`). This is a hard
  switch, not a dual-write — see `UserController::updateStep6()`.
- **Monthly payroll processing** (`MonthlyPayrollController::store()`) branches to
  `processEmployeeMonthlyPayrollDynamic()`, which calls `PayrollCalculationEngine::calculate()`
  and persists the result via `MonthlyPayroll::create()` with `engine_version = 'dynamic_v1'`.
- **Legacy screens** (Payroll Masters, employee salary form) become read-only —
  `App\Traits\BlocksLegacyPayrollWrites` redirects any write attempt with an explanatory message,
  pointing the user at Payroll Structures / Employee Payroll Structures instead.
- **Display** for read-only consumers that haven't been rebuilt around the dynamic catalog
  (`AI\TeamController`, `AI\ProfileController`, `AttendanceAnalyticsService`) keeps working via
  `PayrollStructureAssignmentService::toLegacyShapedArray()`, which presents a dynamic structure in
  the same flat shape a `UserPayroll` row used to have.

**What never changes**: historical `monthly_payrolls` rows — payslips already generated and paid
under the legacy engine — are **never rewritten**. Cutover only affects new payroll runs going
forward. This is deliberate (see `PayrollBackfillComponentCatalog`'s own docblock: "Never touches
monthly_payrolls, user_payrolls, or payroll_masters") and is the standard, safe migration practice:
rewriting already-paid history is not something this guide recommends or provides tooling for.

## 2026-09-19 update — Payroll Structure / Monthly Payroll overhaul

A full review of the dynamic engine's Structure/Monthly-Payroll flow found and fixed:

- **🔴 Fixed a blocking bug**: every snapshot-write path (`PayrollEmployeeStructureController::store()`,
  and all of `PayrollStructureAssignmentService`'s write methods) hardcoded
  `calculation_base_type => 'none'` onto the `payroll_employee_components` snapshot, never copying
  the catalog's real base config. So "percentage of another component" silently computed ₹0 for
  every assigned employee, regardless of catalog configuration — this was true before today's other
  changes too, not something introduced by them.
- **New multi-base percentage**: a percentage component (PF/ESIC/PT/TDS, or any Employer
  Contribution) can now sum its percentage base across *several* Earnings components (e.g. "12% of
  Basic + HRA") instead of one. New tables `payroll_component_base_components` (catalog) /
  `payroll_employee_component_bases` (per-employee snapshot) — see `docs/database.md`.
  `PayrollCalculationEngine::resolveBase()` sums over the multi-base set, falling back to the legacy
  single FK only when the pivot is empty (so pre-migration snapshots keep resolving). Catalog UI:
  `payroll/components/index.blade.php`'s "Calculate % Of" field is now a multi-select restricted to
  Earnings components.
- **Structure-scoped assignment enforced**: `payroll/employee-structures/index.blade.php` now
  disables (not just leaves unchecked) any component outside a selected structure template, and
  `PayrollEmployeeStructureController::store()` rejects a submission containing a component outside
  the chosen structure server-side (422), rather than silently accepting it.
- **Monthly Payroll Edit/Update got a real dynamic-engine branch** — previously `edit()`/`update()`
  were 100% legacy-flat (`UserPayroll`'s fixed columns) even for tenants on the dynamic engine; only
  *creation* (`store()`) branched dynamically. Gated on the **payroll row's own** `engine_version`
  (not just the tenant flag — a tenant can flip to dynamic after some payrolls already exist under
  the legacy engine; those old rows must keep using the legacy form). When dynamic:
  Earnings/Deductions/Employer fields render only the employee's actual structure-selected
  components (`PayrollCalculationEngine::resolveEmployeeComponents()`, the same resolver the salary
  slip uses); Week Off/Holiday/Absent become genuinely editable (Absent is bidirectionally linked to
  Present so the day-count identity `present+paidLeave+weekOff+holiday=totalDays` can't go
  inconsistent); a new read-only `POST monthly-payrolls/{id}/recalculate-preview` endpoint drives a
  live preview through the exact same `calculate()` call the eventual save uses, so the preview can
  never drift from what's persisted. Legacy-engine payrolls are completely untouched by this branch.
- **Salary slip fully dynamic**: `monthly-payroll/pdf.blade.php`'s hardcoded ~17-row fixed table
  (plus a separately bolted-on "extra dynamic components" tail loop) is replaced with two
  independent, zero-suppressed lists built from `resolveEmployeeComponents()` — an employee with
  only Basic Salary now sees only Basic on their slip. **Update (2026-09-19, same day)**: the
  Employer Contribution/CTC table is now unconditionally hidden on the slip for everyone, not just
  the employee view (`@if (false)` in `pdf.blade.php` — was `@if (! ($isEmployeeView ?? false))`;
  the `isEmployeeView` flag/threading into `Api/Payroll/PayrollController.php`'s three employee
  endpoints is left in place, just no longer consulted for this). Also masked: PAN, Account No.,
  IFSC, UAN No., ESI No. now show only the last 4 characters (`$maskId()` closure at the top of
  `pdf.blade.php` — a closure, not a named function, since this view can render more than once per
  request during bulk payslip export). Legacy-engine slips keep their fixed layout but gained the
  same zero-row suppression.
- **"Basic Salary" is now a fixed, always-present component.** `PayrollComponentController::update()`
  rejects any name/type change to the `code = 'basic'` row (422), and `updateStatus()` rejects
  deactivating it — every structure/slip assumes at least Basic Salary is always available as the
  universal earnings base. The UI locks the row's status toggle and its Edit modal's name field to
  match. Provisioning: `payroll:backfill-component-catalog` (already idempotent — skips any
  tenant/code pair that already exists) is now scheduled daily (`routes/console.php`, 00:10) in
  addition to its existing manual/cutover uses, so a tenant provisioned later by the separate
  hrm-superadmin app (which writes directly to the shared `tenants` table — this app has no create
  event to hook) still gets Basic Salary and the rest of the platform template set within a day
  without a manual `--tenant=` run. Confirmed via a live run (2026-09-19) that all 6 current tenants
  already have it.
- **Bulk action route fixed** (`monthly-payrolls.bulk-update`): the JS was sending `ids` as a single
  JSON-encoded string via a native form submit, while the controller validated it as a real array
  and returned a JSON response a native submit can't consume — neither side matched the other. Fixed
  by sending real `ids[]` fields and switching the controller to `redirect()->back()->with(...)`,
  matching the page's own `updateStatus()` action (there was no existing AJAX pattern on this page
  to match instead).

## Why this uses Artisan commands, not a hand-written SQL script

A substantial migration path already exists in this codebase from an earlier "Payroll rebuild
Phase 1–9" effort — it just was never fully rolled out (only tenant 7 has ever had the flag
flipped). Three commands already do the real work, and are transactional, idempotent, and
`--dry-run` capable:

- `payroll:backfill-component-catalog [--tenant=] [--dry-run]` — seeds the platform component
  templates, clones them into each tenant's own `payroll_component_master` catalog, and converts
  every `user_payrolls` row into a `payroll_employee_structures` + `payroll_employee_components`
  snapshot. Safe to re-run — skips anything already backfilled.
- `payroll:resync-drifted-structures [--tenant=] [--dry-run]` — catches any employee whose legacy
  "current" salary has no matching current dynamic structure (e.g. edited after the backfill ran).
- `payroll:engine-diff [--tenant=] [--month=] [--tolerance=]` — computes what the dynamic engine
  *would* produce for real historical payslips and compares it against what was actually persisted
  by the legacy engine, reporting any drift beyond tolerance.

A hand-written SQL script would have to reimplement all of this — the tenant-scoped component
cloning, the flat-field-to-component-code mapping, the idempotency checks — with none of the
built-in dry-run/rollback safety, against real production financial data. That tradeoff isn't worth
it, so this guide builds on the existing commands instead of introducing a parallel path.

## The safe on-ramp: `payroll:cutover-tenant`

```
php artisan payroll:cutover-tenant {tenant-id} [--dry-run] [--tolerance=1.0]
```

Checks, in order, before ever writing anything:

1. **Component catalog complete** — every code the platform's component templates define exists
   in this tenant's `payroll_component_master`.
2. **No drifted structures** — every employee with a current legacy salary has a matching current
   dynamic structure.
3. **Engine-diff parity** (only if the tenant has any legacy `monthly_payrolls` history) — runs
   `payroll:engine-diff --tenant={id}` and refuses to proceed if it reports drift beyond
   `--tolerance` (₹1 default).

Only if every check passes does it flip `payroll_dynamic_ui_enabled = 1` for that tenant and log
the cutover. `--dry-run` runs every check and reports the outcome without writing anything —
confirmed in this session to make no DB changes even when every check passes.

If any check fails, the command tells you exactly which of the other two commands to run first
(with the right `--tenant` flag) — it never tries to fix the gap itself, so you always see what
changed and why.

## Recommended sequence, per tenant

```
php artisan payroll:backfill-component-catalog --tenant={id} --dry-run   # review
php artisan payroll:backfill-component-catalog --tenant={id}             # apply
php artisan payroll:resync-drifted-structures --tenant={id} --dry-run    # review
php artisan payroll:resync-drifted-structures --tenant={id}              # apply
php artisan payroll:cutover-tenant {id} --dry-run                        # review
php artisan payroll:cutover-tenant {id}                                  # apply
```

Do **one company at a time**. Confirm the first payroll run after cutover looks right (compare
against what the legacy engine would have produced for a similar month, via `engine-diff` against
the *previous* month's legacy data if available) before moving to the next tenant.

## Before running any of this against production

1. **Take a database backup first.** This is real, already-relied-upon payroll data for real
   companies — treat the backup as non-negotiable, not optional.
2. **Run against a staging copy of production data if one exists**, before touching production
   itself.
3. **Check for the H2 inverted-range pattern** before cutover, since a tenant's real historical
   data may have a version of the bug this session found and fixed locally (`payroll_employee_structures`
   id 147):
   ```sql
   SELECT * FROM payroll_employee_structures WHERE effective_to < effective_from;
   ```
   If any rows come back, decide the correct `effective_to` (or null it to reopen the range) before
   relying on that employee's structure history — don't guess; this session deliberately didn't
   fabricate a replacement date for the one local row it found, for the same reason.
4. **PF/ESI are safe out of the box** if you seed `StatutoryRateConfig` rows with the standard
   published Indian rates (PF 12%/12%, ₹15,000 wage ceiling; ESI 0.75%/3.25%, ₹21,000 wage
   ceiling) — these are government rates, not tenant-specific guesses, and are already correctly
   configured for every active tenant in the local dev database.
5. **PT stays manual** until real, verified state-specific slab data is entered via the Statutory
   Compliance screen (`statutory_pt_slabs`) — this repo has no seedable universal PT rate, and none
   was fabricated as part of this work.
6. **Rollback is just flipping the flag back to `0`.** No data is ever deleted by any command in
   this guide — legacy `user_payrolls` rows are read but never modified during backfill, and
   historical `monthly_payrolls` are never touched at all. Flipping the flag back only affects
   *new* payroll runs and salary edits from that point forward.
7. **Never run `payroll:cutover-tenant` for more than one company per session** without reviewing
   the first company's next real payroll run.
