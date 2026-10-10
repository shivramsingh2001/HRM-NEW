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
--primary: #0D6EFD;        /* primary blue: text, icons, active states — same as --primary-mid since 2026-10-03 (was #1E40AF) */
--primary-mid: #0D6EFD;    /* primary button / accent */
--primary-light: #EFF6FF;  /* light blue background */
--primary-dark: #0B5ED7;   /* hover */
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
| `<x-ui.page-header>` | `<x-ui.page-header title="Pay Batch" back :crumbs="[['label'=>'Expenses','url'=>route('expense.index')]]">` | **The header of every page** (since 2026-10-03 all ~100 app pages use it; no hand-written `.page-header` markup is left). Layout: title │ breadcrumb (Home › crumbs › `current` ?? title) …… [Back] [actions]. Props: `title`, `current`, `parent` (one crumb, route name), `crumbs` (any number, `label` + optional `url`), `back` (**on by default** on every page — previous page, or the dashboard when opened directly / after login / from another site; a URL sends it there instead; `:back="false"` hides it, used only on the dashboards), extra `class` is merged (pages in the `content-area` layout pass `class="content-area-header sticky-top"`). Put buttons in `<x-slot:actions>`; they get a uniform 32px size, `.btn-primary` = blue `#0D6EFD`/white, `.btn-light-brand` / `.btn-light` = white with blue text, `.btn-icon` = 32px square. Styles: "PAGE HEADER — shared design" in theme-custom.css; Back button = `.ph-back`. Don't restyle `.page-header` / `.breadcrumb` / `.page-header-title h5` in a page's own CSS |
| `<x-ui.stat-card>` | `<x-ui.stat-card icon="users" label="Total Employees" value="128" pill="This month" />` | The canonical KPI tile — replaces `.kpi5-card`/`.stats-card`/`dashboard-stat-card` copies. `icon` is a Feather icon name (no prefix). Optional `valueId` prop puts an `id` on the `.kpi5-value` element for JS/AJAX-updated tiles; extra HTML attributes (e.g. `id`, `class`) merge onto the root `.kpi5-card` |
| `<x-ui.filter-card>` | `<x-ui.filter-card title="Filter Team Members" :clear-url="$active ? route('team.index') : null"> <form …> <div class="filter-row"> <div class="filter-item"> … </div> </div> </form> </x-ui.filter-card>` | **The filter section of every list / report page** (design reference: Team → "Filter Team Members"). White card, filter-icon chip + 11.5px heading, optional "Clear Filters" link, then the page's own form unchanged. Shared styles "FILTER CARD" in theme-custom.css also style hand-written `.filter-wrapper > .filter-header > .filter-title` + `.filter-row > .filter-item` markup (`.filter-section` = old alias). Inside it every input / select / `.btn` is 34px (grey fill, blue focus), labels 11px, `.reset-btn` = white Reset; `.search-wrapper` puts a search icon in the input; `.filter-item.search` (220px), `.grow`, `.narrow` size items; ≤768px the row stacks. Don't restyle these classes in a page's own CSS |
| `<x-ui.pagination-footer>` | `<x-ui.pagination-footer :paginator="$teamData" label="members" />` | **The list footer of every paginated table** (reference: Team): a `.card-footer` with "Showing x to y of z {label}" left and the page boxes right (keeps the query string, bootstrap-4 links). Shown whenever there is at least one row; `:always="false"` = only when there are 2+ pages |
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

## Top header height (`--header-h`)

The fixed top bar (`.nxl-header`, `client/layout/header.blade.php`) is **56px** (theme default was 80px). `theme-custom.css` → "TOP HEADER — compact" sets `:root { --header-h: 56px; }` and re-points every theme rule that was hard-coded to the header height (content `top`, sidebar logo strip `.m-header`, `.navbar-content` height, fixed side panels, mega-menu, mobile header). To resize the header change only that variable; anything a page pins under the header should use `top: var(--header-h)` rather than a px value.

## Report sub-tabs (`.rpt-subnav`)

Every report page shows a row of pill tabs under the header listing the other reports in the same Reports-hub group, so users switch reports without going back to the hub. Leave and Payroll render it inline (`report/leave/reports.blade.php`, `report/payroll/reports.blade.php`); Attendance, Project, Task, Expense and Asset use the shared partial `@include('client.report.partials.report-subnav', ['group' => 'attendance|project|task|expense|asset'])` as the first child of the page body. The partial holds the tab list per group (same feature gates as the hub cards — e.g. Overtime/Shift tabs only when those features are on) and marks the current one active via `request()->routeIs()`. When adding a report, add it to both the hub card list and that partial.

## Attendance report employee filters

Every attendance report's filter row includes `@include('client.report.partials.employee-filters', ['selectClass' => '<page input class>', 'deptParam' => 'department|department_id', 'desigParam' => 'designation|designation_id', 'except' => [...]])` — Branch (only with the Branches plan module), Department, Designation and Attendance Location selects that submit on change. Applied server-side by the `FiltersReportEmployees` controller trait. Use it (not hand-written selects) when adding a new attendance report.

## Lazy tab panes (Employee 360 page)

`client/user/user-detail.blade.php` shows the pattern for a page with many tabs: a pane carries `data-lazy="{url}"` and is fetched the first time its tab is shown (`shown.bs.tab`), then marked `data-loaded`; links inside a pane with `data-p360-reload` reload only that pane. Shared styles there: `.p360-kpis`/`.p360-kpi` (KPI tiles), `.p360-table`, `.p360-chip` (`.extra` amber, `.muted` grey), `.p360-sub`, `.p360-note`, `.p360-toolbar`.

Layout (redesigned 2026-10-03): everything sits in `.main-content.p360-page` → `.p360-layout`, a 3-column grid — `.p360-profile` (compact profile card: photo, name, status, `.p360-facts` list, `.p360-quick` action grid), `.p360-nav-wrap` (vertical Bootstrap tab menu `ul#myTab.p360-vnav`, one flat list in the order of `$navGroups`, no group labels; each link carries `data-bs-target` + `data-icon`) and `.p360-main` (panel whose `.p360-pane-head` title/icon follow the chosen tab). The profile card and menu are sticky with their own scroll. Below 1200px the profile card moves to a full-width row on top; below 992px the menu becomes a horizontal, scrollable tab strip. All styles for it are scoped under `.p360-page` in the view's own `@section('style')` — nothing in `theme-custom.css` changed. Add a new tab by adding it to `$navGroups` (server-rendered pane) or `$lazyTabs` / `$lazyIcons` + `EmployeeProfileController::TABS` (lazy pane).

### Profile card (Employee 360 left column)

`.p360-profile` in `client/user/user-detail.blade.php` (CSS scoped to `.p360-*` in that file): blue header strip, 56px avatar (photo or initials) with an active/inactive presence dot, name + designation + email, badges (status, employment type, attendance type), then a compact icon · label · value list — Employee ID, Designation, Department, Phone (tel:), Personal email (mailto:), Joined + tenure, Reports to, Branch (plan-gated), Attendance location. Values come from the already-loaded `$user` (no extra controller queries beyond lazy `branch`/`attendanceLocation`); missing values show a muted "—". Quick actions below are unchanged.

### Action forms on the Employee 360 page

Actions there do not get a modal each. Any element with `data-p360-open="{form url}"` (+ `data-title`, optional `data-size="lg"`) loads a form partial into the single `#p360Modal`; the partial wraps its fields in `<x-p360.form :action="…" reload="leave,attendance">` (`resources/views/components/p360/form.blade.php`), which is posted by AJAX with `FormData` (file inputs work). Success = HTTP 2xx without `success:false` / `status:false`; the modal closes, a toast shows the message, the panes named in `reload` (and the Overview KPIs) refresh — `reload="page"` reloads the page and returns to the open tab. Errors (`errors` bag or `message`) are listed at the top of the form. Use `class="p360-select2"` for a Select2 inside the modal, `urlField="name"` when the endpoint carries the chosen record in its path (`__ID__` in the action), and a small inline `<script>` in the partial for show/hide logic. Row buttons inside `.p360-table` use `btn btn-sm`; icon-only ones use `.p360-icon-btn`; a section's Edit link is `.p360-edit` inside `.section-title`.

Policy forms (Policies tab) reuse the same modal: `forms/policy.blade.php` renders any "one value per setting" section from `EmployeePolicyService::fields($section)` as rows of *custom checkbox · setting · company value · input* (the input is enabled only while its box is ticked — `forms/_policy-script.blade.php`); `forms/policy-leave.blade.php` does the same per leave type. To make a new company setting customisable per employee, add it to `EmployeePolicyService::FIELDS` / `LIMIT_FIELDS` and to `EmployeeProfileController::companyPolicy()` — the tab, form and validation follow automatically.

## Employee picker options (2026-10-06)

Searchable employee selects in popups use select2 with `dropdownParent` set to the modal (without it the search box cannot be typed in) and a `templateResult` that renders each option as `.emp-opt`: round initials avatar, name (employee ID), email underneath. Used by `<x-on-behalf.modal>` (leave, expense, loan, regularization, overtime) and Leave Credit → Add Manual Credit (which adds a balance pill, `.emp-opt-balance`). Options carry `data-name`, `data-empid`, `data-email`. Hover is a light blue row (`#EFF6FF`); text colours do not change.

## Maps

Maps use Leaflet 1.9.4 with OpenStreetMap tiles (no key). The Attendance Sessions page (`client/attendance/sessions.blade.php`) switches to the Google Maps JavaScript API when `GOOGLE_MAPS_API_KEY` is set (`config('services.google_maps.key')`); it falls back to Leaflet when the key is blank, the Google script cannot load, or Google rejects the key (`gm_authFailure`). Leaflet is always loaded there as the fallback. A new map page should follow the same rule: Leaflet by default, Google only behind that key.

## Icon colour

Every content icon uses one colour, `--icon-color: #0D6EFD` (theme-custom.css `:root`). Views write `color: var(--icon-color, #0D6EFD)`; add `.icon-primary` to an icon (or to its wrapper) to give it that colour. Shared rules also colour icons in section/card titles, the page-header title, `<x-ui.stat-card>` tiles and the theme's `.avatar-text` / `.bg-soft-*` icon circles. Deliberately not blue: status icons (green / red / amber), white icons on coloured badges or buttons, and grey placeholder / empty-state icons. The theme's `.text-primary` / `.bg-soft-primary` follow the palette (`#0D6EFD` / `#EFF6FF`).

## Lists: card, table, pagination, row actions (shared, 2026-10-03)

One "LIST UI" block in theme-custom.css (reference: Team page) styles every `.card` (10px radius, 1px border, small shadow), `.card-header` / `.card-title` (white, 12px semi-bold, flex), `.card-footer` (white, top divider), `.table` (`thead th` 10px uppercase #475569 on #f8fafc with a 2px line; `tbody td` 11.5px with a faint divider, row hover #f8fafc, no line under the last row), `.pagination` (36px boxes, 8px radius, active = #0D6EFD) and `.action-btn` (32px light-blue chip with the primary icon; `.delete/.danger/.reject` red, `.approve/.success` green, `.warning` amber; hover fills the chip). Page-level rules for these generic selectors were removed from 59 pages (489 rules); a page may still style its own table (`#myTable th`) or cell content. Don't add generic `.table th`, `.pagination`, `.action-btn`, `.card-header` rules to a page.

## Shift requests & shift reports (2026-10-10)

- **Shift chip** — a shift is shown as its name with the shift's own `color_code`: `.sr-chip` (left border in the shift colour) on the Shift Requests page, `.shift-pill` (colour dot + name) in the Shift Change Log, `.shift-chip` (tinted short label) in the Shift Report matrix. "None" / "No shift" in muted text when there is no shift.
- **Request timeline** — the Shift Requests detail modal ends with a "History" list (`.sr-timeline`: vertical line, primary-colour dot per event; event label bold, then actor · role · channel · time in `.meta`). Built from `ShiftRequestPresenter::detail()['events']`; reuse it for any other request timeline.
- **Swap marker ⇄** — a day whose shift came from a swap shows **⇄** after the shift label (Shift Report matrix `.swap-mark`, manager dashboard team-request lines). Keep the character, not an icon, so it survives CSV/print.
- **Deep links** — `shift.requests.index` accepts `?tab=mine|to_me|approvals|all` and `?open={id}` (opens the detail modal); use them from dashboards, reports and Employee 360 instead of building another request view.
- The Shift Change Log and Shift Requests Register share `client/report/attendance/partials/shift-activity-styles.blade.php` and `shift-activity-script.blade.php` (filters submit on change, debounced search) — same look as the Clock In/Out Log.
