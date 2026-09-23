# UI Guidelines

Reference for the Blade admin panel (`resources/views/client/*`). Read this before building any new page or component so you reuse what exists instead of re-inventing it.

## 1. CSS/JS stack — what's actually loaded

The app ships **two, mostly-unrelated front-end stacks**. Only one is real for admin-panel pages:

- **Real stack (used by every admin page)**: a purchased Bootstrap 5 admin theme served as static assets from `public/assets/` — **not** built through Vite. Loaded in `resources/views/client/layout/head.blade.php`:
  - `public/assets/css/bootstrap.min.css` — Bootstrap 5
  - `public/assets/vendors/css/vendors.min.css`, `daterangepicker.min.css`, `select2.min.css`, `select2-theme.min.css` — vendor plugin CSS
  - `public/assets/css/theme.min.css` — the purchased theme's compiled CSS (source in `public/assets/scss/theme.scss` + `public/assets/scss/bootstrap/`, `public/assets/scss/themes/`)
  - `public/assets/css/theme-custom.css` — **this app's own overrides/design tokens** (see §2), cache-busted with `?v={{ filemtime(...) }}`
  - `https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css` — Toastr (CDN)
  - `dataTables.bs5.min.css` is present but **commented out** — DataTables.js is not actually used (see §5)
  - JS footer (`resources/views/client/layout/foot.blade.php`): `vendors.min.js`, `daterangepicker.min.js`, `apexcharts.min.js` (charts), `circle-progress.min.js`, `common-init.min.js`, `theme-customizer-init.min.js`, `customers-init.min.js`, `select2.min.js` + `select2-active.min.js`, Toastr JS (CDN). jQuery ships inside `vendors.min.js`.
- **Vite/Tailwind scaffold** (`resources/css/app.css`, `resources/js/app.js`, `resources/js/bootstrap.js`, `tailwind.config.js`, `package.json`): this is the **stock Laravel Breeze scaffold** (Figtree font, default Tailwind config) — largely vestigial for the admin panel. `bootstrap.js` only sets up `window.axios` with an `X-Requested-With` header; axios is not the AJAX method actually used on pages (see §6). **Do not build new admin-panel UI on Tailwind** — it isn't wired into any client view.

So: **the admin panel is Bootstrap 5 + a commercial theme, not Tailwind**, despite Tailwind being present in the repo.

## 2. Design tokens — `public/assets/css/theme-custom.css`

Single shared stylesheet (1152 lines), loaded last so it can override the vendor theme. Written by a prior "UI/UX Design Consistency" pass — extracted from what used to be an inline `<style>` block in `head.blade.php`. **Always reference these CSS variables, never hardcode a hex value.**

```css
--primary: #1e3a8a;        /* the app's real primary — used ~575× */
--primary-mid: #2563eb;    /* accent / button shade */
--primary-light: #e3edfe;
--primary-dark: #1d4ed8;
--purple: #7c3aed;  --purple-light: #f5f3ff;  --purple-mid: #ddd6fe;
--success: #059669;  --success-light: #ecfdf5;
--danger: #dc2626;   --danger-light: #fef2f2;
--warning: #d97706;  --warning-light: #fffbeb;
--border: #e5e7eb;   --border-focus: #93c5fd;
--surface: #ffffff;  --surface-2: #f9fafb;  --surface-3: #f3f4f6;
--text-primary: #111827;  --text-secondary: #6b7280;  --text-muted: #9ca3af;
--radius-sm: 6px; --radius-md: 10px; --radius-lg: 14px; --radius-xl: 18px;
--shadow-sm / --shadow-md / --shadow-focus
--font: 'DM Sans', system-ui, sans-serif;
--gray-50 … --gray-900   /* full gray scale */
--fs-xs:10px --fs-sm:11.5px --fs-base:12.5px --fs-md:14px --fs-lg:16px   /* type scale, not fully migrated onto yet */
--sp-1:4px … --sp-6:24px  /* 4px spacing scale, not fully migrated onto yet */
```

Notable global overrides in this file (apply site-wide unless a page has its own more-specific/`!important` rule):
- `.btn-primary` → `--primary-mid` bg, 8px radius, 12px font
- `.form-control` → `padding: 7px 15px !important`
- `.table` → `font-size: 11.5px`; `.table-responsive .table tr td` → `padding: 3px 15px`
- `.table-responsive` → custom `cursor: grab` (see §5, custom drag-scroll — DataTables is not used)
- `.employee-info` / `.employee-avatar` / `.employee-initials` / `.employee-initials-sm` / `.employee-name` / `.employee-email` — the canonical "avatar + name + email" cell pattern, centralized here specifically so pages stop re-declaring it
- `.kpi5-card` / `.kpi5-top` / `.kpi5-icon` / `.kpi5-pill` / `.kpi5-value` / `.kpi5-label` — the canonical compact stat-card anatomy (icon+pill header, bold value+label). Use `<x-ui.stat-card>` (§3) rather than hand-rolling this markup.
- `.custom-employee-dropdown` — searchable employee-picker dropdown styling
- `.grade-badge[data-grade]` — letter-grade→color pill (A/B/C green/blue/amber, D/F red), added for Performance's 10-band grade scale; same one-mapping-for-the-whole-app idea as `.status-badge`
- `.period-toggle` — compact Daily/Weekly/Monthly pill switch (Performance's dashboard/individual-report trend section), styled like a `.filter-tag` chip group

## 3. Reusable Blade components — `resources/views/components/ui/`

A small, well-documented component library exists (each file's own top-comment is the authoritative usage example — read it before using). **Prefer these over hand-rolled markup**; they were added specifically to stop drift across pages (e.g. "approved" used to be blue in Loans and green everywhere else).

| Component | Usage | Notes |
|---|---|---|
| `<x-ui.card>` | `<x-ui.card title="Recent Activity" :bodyClass="'p-0'">…</x-ui.card>` | `title` (optional), `bodyClass`, optional `headerActions` slot |
| `<x-ui.data-table>` | `<x-ui.data-table><thead>…</thead><tbody>…</tbody></x-ui.data-table>` | Thin wrapper forcing `.table-responsive` + shared `.table` styling. Only 60/141 pages currently wrap their tables in `table-responsive` at all — check when touching an older page. `hover` prop (default true) |
| `<x-ui.modal>` | `<x-ui.modal id="addDepartmentModal" title="Add Department">…</x-ui.modal>` | For **small** forms. `bodyOnly` + `<x-slot:footer>` variant for forms needing fixed footer buttons outside a scrolling body |
| `<x-ui.drawer>` | `<x-ui.drawer id="addEmployeeDrawer" title="Add Employee">…</x-ui.drawer>` | Bootstrap offcanvas. For **large** forms only (design rule: modal = small form, drawer = large form). Trigger via `data-bs-toggle="offcanvas" data-bs-target="#id"`. Place inside `@section('create-modal')` (yielded by `master.blade.php` as `@yield('create-modal')`), not inline in page content |
| `<x-ui.page-header>` | `<x-ui.page-header title="Leave Requests" :parent="['label'=>'Leave','route'=>'leave.view']">` | Standardizes the title/breadcrumb block. `parent` adds one breadcrumb level. `<x-slot:actions>` for header buttons |
| `<x-ui.stat-card>` | `<x-ui.stat-card icon="users" label="Total Employees" value="128" pill="This month" />` | The canonical KPI tile — replaces `.kpi5-card`/`.stats-card`/`dashboard-stat-card` copies. `icon` is a Feather icon name (no prefix). Optional `valueId` prop puts an `id` on the `.kpi5-value` element for JS/AJAX-updated tiles; extra HTML attributes (e.g. `id`, `class`) merge onto the root `.kpi5-card` |
| `<x-ui.empty-state>` | `<x-ui.empty-state icon="inbox" title="No leave requests" subtitle="…" />` | "No records" state for list pages — currently ad hoc or missing on most pages; use this for new ones |
| `<x-ui.status-badge>` | `<x-ui.status-badge :status="$leave->status" />` | One canonical status→color mapping via `.status-badge[data-status]` in theme-custom.css. Use instead of inventing new badge classes |

## 4. Layout — `resources/views/client/layout/`

- `master.blade.php` — the shell every page extends (`@extends('client.layout.master')`); includes head/header/sidebar/footer partials and yields `@yield('content')`, `@yield('create-modal')` (see drawer note above), and others — check the file directly for the full yield list before adding a new section name.
- `head.blade.php` — `<head>` asset includes (§1)
- `header.blade.php` — topbar
- `sidebar.blade.php` — left nav. Menu entries gated by a subscription-plan feature are wrapped in `@feature('key') ... @endfeature` (the same directive used on route middleware, see `docs/architecture.md`'s feature-gating row); as of 2026-09-22 this covers most modules (Leave, Holiday, Task, Project, Asset, Expense, Loan, Overtime, Regularization, WFH & Travel, Meetings, Biometric) — previously only the Payroll menu item used this pattern, everything else relied solely on `role:`/`in_array($role, ...)` checks even though the underlying routes were plan-gated.
- `footer.blade.php` — page footer
- `foot.blade.php` — pre-`</body>` JS includes + two site-wide inline scripts: Select2 auto-init (`$('.select2').select2(...)`) and a custom `.table-responsive` wheel/touch/drag-scroll handler (see §5 — this is what stands in for DataTables)
- `impersonation-banner.blade.php` — shown when a super-admin is impersonating a tenant user (one of the few places using FontAwesome (`fas fa-user-secret`) instead of Feather)
- `subscription-banner.blade.php` (2026-09-22) — sticky, inline-styled amber/red banner shown to every logged-in user when `App\Services\SubscriptionStatusService::forTenant()` reports the tenant's subscription is ending soon or already past its end date; same sticky-bar pattern as `impersonation-banner.blade.php`, stacked directly below it in `client.layout.master`

Pages extend the master and fill `@section('content')`; module views live under `resources/views/client/<module>/*.blade.php` (e.g. `client/leave/`, `client/attendance/`, `client/payroll/`).

## 5. Tables

- **No DataTables.js** — `dataTables.bs5.min.css`/`.js` are present but commented out in `head.blade.php`/`foot.blade.php`. Instead, every `.table-responsive` gets a custom vanilla-JS behavior installed globally in `foot.blade.php`: mouse-wheel scroll redirected to the outer `.content-area` (bypassing PerfectScrollbar), touch horizontal-vs-vertical scroll detection, and click-drag horizontal scrolling (`cursor: grab`/`grabbing`).
- Use `<x-ui.data-table>` for new tables so they automatically get `.table-responsive` + this behavior + consistent `.table` styling; many older pages hand-roll `<div class="table-responsive"><table class="table">` directly instead.
- Row font-size is fixed site-wide at 11.5px via `.table` in theme-custom.css.
- Pagination: standard Laravel `{{ $items->links() }}` (Bootstrap-styled paginator), e.g. `client/announcement/all.blade.php`, `client/attendance/manage.blade.php`, `client/branch/branch.blade.php`.

## 6. Forms, validation, AJAX

- Validation errors: Blade `@error('field')` directive + Bootstrap `.is-invalid` class, e.g. `client/user/add-user.blade.php`. Multi-step forms (like add-user) manage `.is-invalid` manually via jQuery when validating a step client-side before submit.
- AJAX is done with **jQuery `$.ajax`**, not axios/fetch, despite axios being wired in `resources/js/bootstrap.js`. CSRF token is read from a meta tag and passed per-request:
  ```html
  <!-- master.blade.php -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  ```
  ```js
  headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  ```
  (repeated inline per AJAX call across views, e.g. `client/announcement/all.blade.php`, `client/attendance/manage.blade.php` — no global `ajaxSetup` currently centralizes this)
- Select dropdowns: Select2 (`.select2` class), auto-initialized globally in `foot.blade.php`.
- Date ranges: `daterangepicker` vendor plugin.

## 7. Alerts / notifications

- **Toastr** (CDN) is the standard for transient success/error notices — `toastr.success(...)`, `toastr.error(...)` — used across most AJAX-driven pages (e.g. `client/announcement/*`, `client/attendance/*`).
- Server-rendered flash messages use Laravel `session()->flash(...)` + Bootstrap alert markup for non-AJAX (full page reload) form submissions.

## 8. Badges / status colors

Two systems coexist:
- **New/canonical**: `<x-ui.status-badge :status="..." />` → `.status-badge[data-status="..."]` in theme-custom.css. Use this for anything new.
- **Legacy/ad hoc**: raw `<span class="badge badge-success|badge-danger|badge-info|badge-purple|badge-secondary|badge-dark|badge-credit|badge-debit|...">` scattered across older views and some JS-built rows (e.g. `class="badge ${data.status_badge || 'bg-secondary'}"`). When touching an old page, prefer migrating it to `<x-ui.status-badge>` rather than adding another one-off badge class.

## 9. Icons

- **Feather icons** (`feather-*` classes, e.g. `<i class="feather-inbox"></i>`) are the primary/current icon set — used in ~105 view files and in every new `x-ui.*` component.
- **Font Awesome** (`fas fa-*`) still appears in ~25 older view files (e.g. the impersonation banner). Don't introduce new FontAwesome usage — use Feather to match the current direction.

## 10. Employee avatar/name/email cell

A recurring pattern across list pages (leave, attendance, team, payroll, etc.): circular avatar (photo or `.employee-initials` fallback with `--primary-mid` background) + name (`.employee-name`, 600 weight) + email (`.employee-email`, 8px) in a flex row (`.employee-info`). Styled once in theme-custom.css — reuse this markup/class set rather than rebuilding per page.

## 11. Known inconsistency (in progress)

`docs/architecture.md` / project memory note a prior "UI/UX Design Consistency" initiative (see the header comment in `theme-custom.css`) that centralized colors, the employee-info cell, and the KPI card, and introduced the `x-ui.*` component library — but migration is **partial**: only 60/141 pages use `table-responsive` consistently, badges/status colors are still split between the new component and legacy ad hoc classes, and the type/spacing scale (`--fs-*`/`--sp-*`) isn't applied everywhere yet. When editing an old page, prefer adopting the canonical component/token over copying that page's existing ad hoc pattern.
