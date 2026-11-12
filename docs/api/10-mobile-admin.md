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

### `GET /api/admin/team/{id}` (`?month=YYYY-MM`, default this month)
One member's whole screen in **one call** (same company + your team scope, else 404 / 403). The
response uses **the same keys as the employee app's `GET /api/user/team-profile/{id}`**
(`docs/api/02-employee-self-service.md`), so the app can reuse that screen:

```json
{"success": true, "message": "User profile and attendance data fetched successfully", "data": {
  "personal_information": {"id": 112, "name": "…", "employee_id": "EMP1012", "email": "…", "contact": "…", "role": "employee"},
  "teams": [{"id", "employee_id", "name", "email", "role", "designation", "department", "profile_image",
             "punch_in", "punch_out", "clock_in_lat", "clock_in_long", "clock_in_address",
             "clock_out_lat", "clock_out_long", "clock_out_address", "status", "is_week_off"}],
  "basic_details": {"father_name", "mother_name", "dob", "gender", "profile_image", "blood_group", "marital_status",
                    "nationality", "alternate_phone", "personal_email", "aadhaar_no": "XXXXXXXX4821", "pan_no": "XXXXXX912K", "languages": []},
  "bank_details": {"account_number": "XXXXXXXX6789", "ifsc", "bank_name", "branch_name"},
  "job_details": {"designation", "department", "joining_date", "employment_type", "reporting_head",
                  "reporting_heads": [{"id", "name", "is_primary"}]},
  "location": {"country", "state", "city", "country_code", "state_code", "city_code", "address", "permanent_address", "pincode"},
  "attendance_summary": {"month": "November 2026", "month_start": "2026-11-01", "month_end": "2026-11-30",
    "counts": {"total_days": 12, "present": 8, "absent": 1, "on_leave": 0, "holiday": 0, "weekend": 2,
               "checked_in_only": 0, "half_day": 0, "work_days": 10, "upcoming": 18},
    "today": {…one attendances row…, "location_tracks": [{"track_time", "latitude", "longitude", "address"}], "total_tracks": 0}},
  "attendance_details": {"period": {"start_date": "2026-11-01", "end_date": "2026-11-30"},
    "attendances": [{"user_id", "name", "email", "date": "2026-11-03", "day_name": "Tuesday",
                     "clock_in": "2026-11-03 09:02:11", "clock_out": "2026-11-03 18:05:40", "total_hours": "9.05",
                     "leave_type", "leave_type_name", "leave_reason", "leave_session", "holiday_name", "message",
                     "day_status": "Present", "task_count": 2,
                     "clock_in_time": "09:02 AM", "clock_out_time": "06:05 PM", "day_value": 1, "day_label": null,
                     "shifts": [], "details": {…}}, …]},
  "documents": {"experience_letter", "tenth_marksheet", "twelfth_marksheet", "highest_qualification_certificate"},
  "documents_list": [{"id", "document_type", "document_type_label", "document_name", "file_url"}]}}
```

What differs from `team-profile` (the keys are the same):
- **Month:** `?month=YYYY-MM` picks the month (a wrong or future month falls back to this month).
  `attendances` = one row per day of that month, oldest first, from the 1st (or the joining day) to
  the month end; days after today are `Upcoming` (or a planned Holiday / leave / Week Off) with no times.
- **Masked:** `aadhaar_no`, `pan_no`, `account_number` show the last 4 characters only.
- **Values as the web Team page:** `day_status` = `Present`, `halfday`, `Absent`, `Checked In Only`,
  `Full Day Leave`, `First Half Leave`, `Second Half Leave`, `Holiday`, `Week Off`, `Upcoming`
  (team-profile says `Weekend`). `counts` cover the days up to today (`upcoming` = days after today);
  `present` includes checked-in-only days and 0.5 per half day. `leave_type` and `leave_type_name`
  both carry the leave type's name.
- **Extra keys on each day:** `clock_in_time` / `clock_out_time` (ready to show; overnight
  `07:10 AM (+1 day)`), `day_value` / `day_label` (2-shift day worth, `Present ×2`), `shifts` (2+ shifts),
  `details` (tap card: shift, late / early / OT minutes, regularized / marked by, locations; `null`
  when no attendance and no rostered shift).
- **Errors:** proper HTTP codes (403 / 404 / 500 with `success: false`), not 200.

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
