# Mobile Admin API — Dashboard, Team, Reports (`/api/admin/*`)

Added 2026-11-11. Lets **Admin** and **HR** accounts use the mobile app for three screens: the admin
dashboard, the Team page and the Reports. Everything here is the same data the web admin panel shows
— each endpoint reuses the web page's service, so numbers always match the web.

## Login

`POST /api/login` and the OTP login (`/api/send-otp` → `/api/login-otp`) now accept `admin` and `hr`
accounts as well as `employee` / `manager` (before: "You are not authorized to login."). Request and
response are unchanged. Use `data.access.role` from the login response:

- `admin` / `hr` → show the admin screens (this document). An `admin` usually has no shift and does
  not punch — hide the employee self-service screens (clock in etc.) for `admin`. `hr` is normally an
  employee too and keeps the employee screens.
- `employee` / `manager` → the app as before. These roles get **403** `{"success": false, "message": "Only Admin and HR can use this."}` on every `/api/admin/*` call.

## Common rules

- Headers: `Authorization: Bearer <token>` and `Device-Token: <last_login_token>` — same as every
  mobile call (`auth:api` + `singleLogin`).
- Every response is JSON (the group forces `Accept: application/json`): validation errors are **422**
  `{"message", "errors": {field: [..]}}`, a refused call **403**, unknown record **404**.
  Success bodies are `{"success": true, "data": ...}` unless noted.
- Middleware: `mobile.admin` (`App\Http\Middleware\MobileAdminApi` — JSON + refuses with 403 when the
  user's company is not active / not resolved) and `role:admin,hr`. Team routes also need
  `permission:team,view`. Each report follows its own web route's access (below).

---

## Dashboard

### `GET /api/admin/dashboard`

Query (optional) — the web dashboard's period filter:
`range` = `today` | `last_7_days` | `this_month` | `previous_month` | `custom` (+ `from`, `to` = `YYYY-MM-DD`,
max one year). No `range` = each card's normal view (attendance today, chart last 7 days, joinings this month).

`data` sections (`App\Services\Dashboard\AdminDashboardPayload` over `AdminDashboard::build()`):

| key | content |
|---|---|
| `range` | `preset, label, from, to, days, note, is_today, applied` |
| `show` | which cards the company's plan / your permissions allow (`attendance, leave, tasks, projects, expenses, regularization, holiday, announcements, payroll, loans, overtime, assets, meetings`) |
| `employees` | `total, active, inactive, joinings` |
| `attendance` | employee-days over the period: `present, half_day, on_leave, holiday, week_off, absent`, late / early counts … (`null` when attendance is off) |
| `attendance_chart` | per-day series `{days[], present[], absent[], average, totalPresent, totalAbsent}` |
| `pending_approvals` | "Needs your action" items per module |
| `payroll`, `loans`, `overtime` | the month's snapshot (month of the period end) |
| `assets`, `meetings` | now |
| `tasks`, `projects`, `expenses`, `regularization` | counts for the period |
| `top_performers` | `{month, month_options, employees}` (`performance_month=YYYY-MM` picks the month) |
| `department_distribution`, `recent_joinings`, `recent_leaves`, `recent_announcements`, `upcoming_holidays`, `calendar_holidays` | lists |
| `celebrations` | `{today, upcoming_birthdays, upcoming_anniversaries}` |
| `company_trend` | last 6 months |

A card hidden by the plan / permissions is `null`. Dates are `YYYY-MM-DD` (or `YYYY-MM-DD HH:MM:SS`);
every stored photo path (`profile_image`, `photo`, `image`) has a ready `<key>_url` next to it.

---

## Team

### `GET /api/admin/team`

| query | |
|---|---|
| `date` | `YYYY-MM-DD`, default today |
| `status` | `present` (includes half day + checked-in-only) · `absent` · `on_leave` · `holiday` · `weekoff` · `halfday` · `checked_in_only` |
| `search` | name / employee ID / email |
| `branch_id`, `department_id`, `designation_id` | filters |
| `page`, `per_page` | default 1 / 20, max 100 |

```json
{"success": true, "data": {
  "date": "2026-11-11",
  "counts": {"present": 40, "absent": 5, "on_leave": 2, "holiday": 0, "weekoff": 3, "checked_in_only": 6, "halfday": 1, "total": 50},
  "members": [{"id": 12, "employee_id": "EMP012", "name": "…", "email": "…", "designation": "…", "department": "…", "branch": "…",
               "profile_image_url": "https://…", "status": "Present", "clock_in": "2026-11-11 09:02:11", "clock_out": null,
               "total_hours": null, "shifts": [{"id": 9, "name": "General", "time": "09:00 AM–06:00 PM", "color": "#4f46e5", "is_extra": false}],
               "shift_sessions": [], "open_shift": null}],
  "pagination": {"page": 1, "per_page": 20, "total": 50, "last_page": 3}}}
```

`counts` are for the filtered list (same as the web cards). `shift_sessions` is filled only on a day
with 2+ shifts: `{name, in, out, is_extra, state: present|checked_in|not_started}`.

### `GET /api/admin/team/filters`
`{branches[], departments[], designations[], leave_types[], statuses[]}` — each `{id, name}`.

### `GET /api/admin/team/{id}` (`?month=YYYY-MM`)
One member (same company + your team scope, else 404 / 403):
`header` (name, role, image, designation, department, employee_id, email, contact, reporting_head,
joining_date, `today` status, `shifts`), `personal` (… Aadhaar / PAN masked to the last 4),
`job`, `address`, `bank` (account number masked), `documents` (links), `month`, `month_summary`,
`direct_reports` (a manager's reportees with today's status).

### Member attendance (the web member page's own JSON endpoints)
- `GET /api/admin/team/{id}/attendance-stats?month=YYYY-MM` → `{status, summary, month_name, date_range}`
- `GET /api/admin/team/{id}/attendance-calendar?start_date&end_date` → one calendar event per day
- `GET /api/admin/team/{id}/attendance-table?start_date&end_date` → `{status, data: [{date, day_name, clock_in, clock_out, total_hours, day_status, badge_class}], total, date_range}`

### Mark attendance (same endpoints, validation, rules and audit as the web Team page)
- `GET /api/admin/team/shift?user_id=&date=` → the employee's shift that day + `shifts` (pick one when 2+).
- `POST /api/admin/team/mark-attendance` — `user_id, date, end_date?, status (present|absent|half_day|on_leave|first_half_leave|second_half_leave …), clock_in?, clock_out? (HH:MM), clock_out_only?, clock_out_next_day?, leave_type_id? (leave statuses), remarks?, user_shift_id?` → `{success, message, data, is_update, leave_created, shift_details}`.
- `POST /api/admin/team/mark-attendance-bulk` — `user_ids[] (≤200), date, end_date? (≤31 days), status, use_shift_times?, clock_in?, clock_out?, clock_out_next_day?, leave_type_id?, remarks?`.

---

## Reports

All 32 scheduled-report catalog reports (`App\Support\ReportCatalog`): Attendance (9), Leave (5),
Payroll (4), Expense (3), Task (4), Project (4), Asset (1), Site Visits (2). Each is produced by
replaying the report's web **Export** route as you (`ReportFileGenerator::generateFor`), so access,
filters and numbers are the web's.

### `GET /api/admin/reports`
Only the reports you may open (plan + role + permission of the web route):

```json
{"success": true, "data": {
  "groups": [{"name": "Attendance", "reports": [
     {"key": "attendance.day", "label": "Daywise Attendance", "period_type": "day",
      "presets": [{"value": "yesterday", "label": "Yesterday"}, {"value": "today", "label": "Today"}]}]}],
  "branches": [{"id": 1, "name": "…"}], "departments": [{"id": 3, "name": "…"}]}}
```

`period_type` tells the app which date picker to show: `day` → `date`, `month` → `month`,
`range` → `from` + `to` (max 93 days), `none` → no date.

### `GET /api/admin/reports/{key}`

| query | |
|---|---|
| `period` | a preset from the catalog (default: the report's first preset) |
| `date` / `month` / `from`+`to` | a picked date instead of a preset (by `period_type`) |
| `branch_id`, `department_id` | employee filters (applied by the reports that support them) |
| `search` | keeps rows containing the text |
| `page`, `per_page` | default 1 / 50, max 200 |

```json
{"success": true, "data": {
  "report": {"key": "attendance.day", "label": "Daywise Attendance", "group": "Attendance"},
  "period_label": "10 Nov 2026",
  "title_lines": ["Daywise Attendance Report", "Date: 10 Nov 2026"],
  "columns": ["Employee", "Employee ID", "Status", "Clock In", "…"],
  "rows": [["Asha", "EMP001", "Present", "09:02 AM", "…"]],
  "pagination": {"page": 1, "per_page": 50, "total": 120, "last_page": 3}}}
```

All cells are strings, exactly as in the export file. Errors: unknown key **404**; no access **403**
`{"success": false, "message": "<reason>"}`; wrong date for the report type or range over 93 days **422**.

### `GET /api/admin/reports/{key}/download`
Same query (without search / paging) → the CSV / XLSX file (`Content-Disposition: attachment`) for
open / share on the phone.

---

Code: `routes/api.php` (admin group), `App\Http\Controllers\Api\Admin\{Dashboard,Team,Report}Controller`,
`App\Services\Dashboard\AdminDashboardPayload`, `App\Services\Team\{TeamListService,TeamMemberProfile}`,
`App\Services\Reports\{MobileReportService,ReportTableReader,ReportFileGenerator}`. Test: `tests/Feature/MobileAdminApiTest.php`.

Word version for the app developer (same content, with full examples): `docs/api/HRM-Mobile-Admin-API.docx`.
