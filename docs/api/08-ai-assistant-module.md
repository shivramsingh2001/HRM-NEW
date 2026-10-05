# AI Assistant Module — Mobile/Legacy API

Base URL: `https://vpshrms.shurttech.com`

All endpoints below live in `routes/api.php`, nested inside `Route::middleware('tenant')` →
`Route::middleware(['auth:api', 'singleLogin'])` → `Route::prefix('ai')`. They are a **read-only
data-export surface** — one `GET` endpoint per HR domain, each named `view_ai_all()` on its own
tiny controller under `app/Http/Controllers/AI/`. The naming and the shape of the payloads
(heavy on `summary`/aggregation blocks alongside the raw `data`) strongly suggest this group feeds
an AI assistant / chatbot with the caller's own HR context (attendance, leave, tasks, team, etc.)
rather than being consumed by the main Flutter UI screens. Every endpoint scopes its result set to
what the authenticated user is allowed to see (own / team / company), mirroring the same
role/RBAC scoping used by the "real" module endpoints elsewhere in `routes/api.php`.

**This surface has no fixed response envelope** — same "legacy/mobile API" convention as the rest
of `routes/api.php`: raw `response()->json([...], $code)`, ad hoc per-controller. Field names and
status codes below are taken directly from each controller's actual code, not standardized.

## Shared conventions for every endpoint in this module

**Auth:**
| Header | Required | Notes |
|---|---|---|
| `Authorization` | Yes | `Bearer <JWT access token>` issued by `POST /api/login` (or `/api/login-otp`). Missing/invalid/expired/blacklisted token → `401 {"message":"Unauthenticated."}` (mapped centrally from `JWTException` in `bootstrap/app.php`). |
| `Device-Token` | Yes | Must match `users.last_login_token` (set at login). Mismatch → `401 {"status":false,"message":"Your account is logged in on another device. Please login again."}` (`CheckSingleDeviceLogin` middleware). |
| `Accept` | Recommended | `application/json` |

**Tenant scope:** resolved solely from the JWT user's `tenant_id` (`TenantMiddleware::handleApiRequest`) — never client-supplied on this authenticated surface.

**Permissions:** each endpoint independently re-derives a view "scope" (`own` / `team` / `company`) — either via `RbacService::scopeFor($user, module, 'view')` (newer controllers) or a hardcoded `switch ($authUser->role)` (older controllers still using the plain `role` column: `admin`/`hr`/`manager`/`employee`). See the per-endpoint table for which mechanism and which module key is used. Every endpoint rejects an unauthorized caller with `403` and `success: false` (four controllers used to return `200` here — fixed 2026-10-05).

**Pagination:** none, except `GET /api/ai/request` (`per_page` triggers Laravel pagination; omit it to get the full unpaginated set).

**File uploads:** none on any endpoint in this module (all `GET`).

**Common error shape when uncaught exceptions occur:** `{"success": false, "message": "<generic text>"}`, HTTP `500`. The exception itself is written to the log (`Log::error`) and never returned in the body (several endpoints used to append the raw exception/SQL text; `team` used to `dd()` — fixed 2026-10-05).

A full Word reference with real captured responses lives at `docs/api/HRM-AI-API-Documentation.docx`.

---

## GET /api/ai/attendance-locations
**Purpose:** Location/GPS tracking points for the caller's own, their team's, or the whole company's attendance records (depending on scope), for a given day or date range.
**Controller:** `App\Http\Controllers\AI\AttendanceLocationController::view_ai_all`
**Auth:** JWT bearer + Device-Token (see shared conventions above)
**Tenant scope:** `session('tenant_id') ?? $authUser->tenant_id`; `400` if neither resolves.
**Permission/role required:** `RbacService::scopeFor($authUser, 'attendance', 'view')` — must resolve to `own`, `team`, or `company` (a real granted scope), else `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `user_id` | integer | No | Restrict to one user (still must be within the caller's scope). |
| `date` | string (`Y-m-d`) | No | Single day; used when `start_date`+`end_date` are not both sent. Defaults to today. |
| `start_date` | string (`Y-m-d`) | No | Combined with `end_date` for a range (`whereBetween`); takes priority over `date`. |
| `end_date` | string (`Y-m-d`) | No | See above. |
| `department_id` | integer | No | Filter users by department (`user_job_details.department`). |

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/attendance-locations?start_date=2026-09-01&end_date=2026-09-28' \
  -H 'Authorization: Bearer <jwt>' \
  -H 'Device-Token: <device-token>' \
  -H 'Accept: application/json'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Location data fetched successfully.",
  "data": [
    {
      "user_id": 42,
      "employee_id": "EMP0042",
      "name": "Asha Rao",
      "designation": "Software Engineer",
      "department": "Engineering",
      "date": "2026-09-28",
      "tracks": [
        { "time": "09:31:02", "address": "MG Road, Bengaluru", "latitude": "12.9716", "longitude": "77.5946", "battery": 82 }
      ]
    }
  ]
}
```
If no matching users: `200 {"success": true, "message": "No users found", "data": []}`.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 400 | Tenant not resolvable | `{"success": false, "message": "Tenant not found"}` |
| 403 | Caller has no `attendance:view` scope | `{"success": false, "message": "Unauthorized access"}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "Unable to fetch location data. Please try again later."}` (logged) |

**Special behavior / notes:** only returns a user/date row if it actually has tracking points (rows without tracks are dropped from the result, even if attendance exists). `own`/`team`/`company` scope changes which users are queried at all, not just a filter on the result.

---

## GET /api/ai/request
**Purpose:** Travel/WFH-type "requests" (the `requests` table) list with filters, sorting, optional pagination, and status/type summary aggregates.
**Controller:** `App\Http\Controllers\AI\RequestController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via the `TenantTrait` global scope on the underlying models (no explicit tenant check in this controller).
**Permission/role required:** `RbacService::scopeFor($authUser, 'requests', 'view')` → `company` (no filter) / `team` (team + own) / `own` (own only); any other value → `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | Exact match on `requests.status`. |
| `request_type` | integer | No | `requests.request_type_id`. |
| `employee_id` | integer | No | Only honored when scope is `company` (maps to `requests.user_id`, despite the param name). |
| `from_date` | string (date) | No | `requests.start_date >= from_date`. |
| `to_date` | string (date) | No | `requests.end_date <= to_date`. |
| `search` | string | No | LIKE match across employee name, employee_id, request type name, reason. |
| `sort_by` | string | No | One of `created_asc`, `created_desc` (default), `start_date_asc`, `start_date_desc`, `duration_asc`, `duration_desc`. |
| `per_page` | integer | No | If present, response is paginated; otherwise all matching rows are returned. |

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/request?status=PENDING&sort_by=start_date_desc&per_page=20' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`** (unpaginated form; `data` items come from the joined `requests`/`users`/`request_types` query):
```json
{
  "success": true,
  "message": "Request data fetched successfully!",
  "data": [
    {
      "id": 118,
      "employee_id": "EMP0042",
      "employee_name": "Asha Rao",
      "user_id": 42,
      "user_role": "employee",
      "employee_profile_image": "https://vpshrms.shurttech.com/uploads/profile/42.jpg",
      "request_type": "Work From Home",
      "request_type_id": 2,
      "start_date": "2026-09-25",
      "end_date": "2026-09-26",
      "duration_days": 2,
      "reason": "Internet installation at home",
      "status": "PENDING",
      "comments": null,
      "applied_date": "2026-09-24",
      "created_at": "2026-09-24T10:12:00.000000Z",
      "reporting_head_name": "Vikram Shah",
      "reporting_head_employee_id": "EMP0010"
    }
  ]
}
```
When `per_page` is supplied, the body additionally includes `"pagination": {"current_page","per_page","total","last_page","from","to"}` and `data` becomes the current page's items only.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Scope is not `company`/`team`/`own` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

**Special behavior / notes:** the controller also computes `summary` (totals by status/request-type) and `grouped_by_employee` (when scope isn't `own`) internally, but **both are currently commented out of the returned JSON** — only `data` (and `pagination` when applicable) is actually returned despite the code computing more.

---

## GET /api/ai/attendance
**Purpose:** Day-by-day attendance matrix for a date range, with per-status and per-user summary counts. Status priority per day: `Present` (clock-in + clock-out) / `Checked In Only` → `On Leave` (approved leave covering the date; `status_category` `leave`) → `Holiday` (`holiday`; `holiday_name` is filled) → `Week Off` → `Absent`. Leave, holiday and week-off are scalar subqueries per row (no join fan-out).
**Controller:** `App\Http\Controllers\AI\AttendanceController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** `$authUser->tenant_id ?? Session::get('tenant_id')`; `400` if unresolved.
**Permission/role required:** `RbacService::scopeFor($authUser, 'attendance', 'view')` → `own`/`team`/`company`; `null` → `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `start_date` | string (`Y-m-d`) | No | Defaults to the 1st of the current month. Invalid/malformed values silently fall back to the default (no validation error). |
| `end_date` | string (`Y-m-d`) | No | Defaults to today. If `start_date > end_date` they are swapped. Range is capped to 366 days (end date is clamped). |
| `include_tracks` | boolean | No | Accepted (`filter_var(..., FILTER_VALIDATE_BOOLEAN)`, default `true`) but not currently wired into the output in this version of the controller. |
| `user_id` | integer | No | Only applied for `team`/`company` scope (ignored — forced to self — for `own` scope). |

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/attendance?start_date=2026-09-01&end_date=2026-09-28' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Attendance data fetched successfully",
  "data": [
    {
      "user_id": 42,
      "name": "Asha Rao",
      "employee_id": "EMP0042",
      "date": "2026-09-28",
      "day_name": "Monday",
      "clock_in": "2026-09-28 09:31:00",
      "clock_out": "2026-09-28 18:40:00",
      "clock_in_formatted": "09:31 AM",
      "clock_out_formatted": "06:40 PM",
      "total_hours": "09:09",
      "clock_in_address": "MG Road, Bengaluru",
      "clock_out_address": "MG Road, Bengaluru",
      "status": "Present",
      "status_category": "present",
      "holiday_name": null
    }
  ],
  "summary": {
    "total_records": 28,
    "by_status": { "present": 18, "absent": 2, "weekoff": 5, "checked_in_only": 0, "leave": 2, "holiday": 1 },
    "by_user": [
      { "name": "Asha Rao", "total": 28, "present": 18, "absent": 2, "weekoff": 5, "checked_in_only": 0, "leave": 2, "holiday": 1 }
    ],
    "date_range": { "start": "2026-09-01", "end": "2026-09-28" }
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 400 | Tenant not resolvable | `{"success": false, "message": "Tenant not found"}` |
| 403 | No `attendance:view` scope | `{"success": false, "message": "Unauthorized access"}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "Unable to fetch attendance data. Please try again later."}` (logged server-side, message not leaked) |

**Special behavior / notes:** emits one row per **calendar day in range per user in scope**, even days with no attendance record at all (via a recursive CTE date series `CROSS JOIN` on users), so `data` can be large for wide date ranges/`company` scope — there is no pagination.

---

## GET /api/ai/my-profile
**Purpose:** Full HR profile of the authenticated user only (personal details, bank details, job details, location, current + historical payroll structure, documents).
**Controller:** `App\Http\Controllers\AI\ProfileController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit — only ever loads `Auth::user()`'s own row.
**Permission/role required:** none beyond authentication (always "own data").

**Request parameters:** None

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/my-profile' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Profile data fetched successfully",
  "data": {
    "id": 42,
    "employee_id": "EMP0042",
    "name": "Asha Rao",
    "email": "asha.rao@example.com",
    "contact": "9876543210",
    "role": "employee",
    "status": 1,
    "is_current_user": true,
    "reporting_to": "Vikram Shah",
    "profile_image": "https://vpshrms.shurttech.com/uploads/profile/42.jpg",
    "personal_details": { "father_name": "...", "mother_name": "...", "dob": "1995-04-11", "gender": "female", "blood_group": "O+", "marital_status": "single", "nationality": "Indian", "alternate_phone": null, "personal_email": null, "aadhaar_no": "XXXX-XXXX-XXXX", "pan_no": "ABCDE1234F", "languages": ["English", "Hindi"] },
    "bank_details": { "account_number": "...", "ifsc": "...", "bank_name": "...", "branch_name": "...", "uan_no": "...", "pf_no": "...", "esi_no": "..." },
    "job_details": { "designation": "Software Engineer", "designation_id": 5, "department": "Engineering", "department_id": 2, "joining_date": "2023-01-10", "leaving_date": null, "employment_type": "full_time", "reporting_head": "Vikram Shah", "reporting_head_id": 10, "office_branch": null, "type": "permanent" },
    "location": { "country": "India", "state": "Karnataka", "city": "Bengaluru", "address": "...", "permanent_address": "...", "pincode": "560001" },
    "current_payroll": { "...": "legacy-shaped payroll structure object, see docs/modules.md Payroll section" },
    "payroll_history": [ { "id": 7, "...": "legacy-shaped payroll structure object" } ],
    "documents": { "experience_letter": null, "tenth_marksheet": null, "twelfth_marksheet": null, "highest_qualification": null }
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 404 | User row not found/inactive (`status != 1`) | `{"success": false, "message": "User not found"}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

**Special behavior / notes:** `current_payroll`/`payroll_history` are built via `PayrollStructureAssignmentService::toLegacyShapedArray()` off the dynamic payroll-structure tables (Phase 9 payroll engine), not the legacy `user_payrolls` table — accurate even for tenants not yet cut over, per the migration backfill guarantee described in `docs/architecture.md`.

---

## GET /api/ai/annuncement
**Purpose:** All currently-active announcements visible to the caller, with acknowledgment-requirement flags.
**Controller:** `App\Http\Controllers\AI\AnnoucementController::view_ai_all` (note: both the route segment `annuncement` and the controller class `AnnoucementController` are misspelled in the source — preserved here verbatim, not a documentation typo).
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via the `TenantTrait` global scope on `Announcement`.
**Permission/role required:** hardcoded `role` switch (`admin`/`hr`/`manager`/`employee` all see the same unfiltered active-announcement list — there is currently no role-based narrowing here despite the switch structure).

**Request parameters:** None read by this controller.

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/annuncement' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Announcements fetched successfully",
  "data": [
    {
      "id": 9,
      "title": "Office closed for Diwali",
      "description": "The office will remain closed on 2026-10-20.",
      "file_url": null,
      "image_url": "https://vpshrms.shurttech.com/uploads/announcements/banner9.png",
      "created_by": { "id": 3, "name": "HR Team", "employee_id": "EMP0003", "role": "hr" },
      "requires_acknowledgment": true,
      "user_acknowledged": false,
      "published_at": "2026-09-20 11:00:00"
    }
  ],
  "summary": { "total": 12, "requires_acknowledgment": 4, "recent": 3 }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | `$authUser->role` matches none of `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

**Special behavior / notes:** `user_acknowledged` is `true` when the caller has a row in `announcement_acknowledgments` (written by `POST /acknowledge-announcement/{id}`) — one query for the whole list. Only `status = 1` (active) announcements are returned; there is no way to fetch inactive/archived ones through this endpoint.

---

## GET /api/ai/project
**Purpose:** All projects visible to the caller (company/team/own scope) with member lists, task-progress counts, and the caller's own assignment role on each.
**Controller:** `App\Http\Controllers\AI\ProjectController::view_ai_all` — **note: this action does not type-hint `Request $request`**, so it reads no query input at all; any query string is ignored.
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `Project`/`ProjectAssign`/`User`.
**Permission/role required:** `RbacService::scopeFor($authUser, 'projects', 'view')` → `company`/`team` (all projects, with an `is_assigned`/`is_project_head` flag computed for the caller) or `own` (only projects the caller is assigned to, via an inner join); any other value → `403`.

**Request parameters:** None (see note above).

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/project' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Projects fetched successfully",
  "data": [
    {
      "project": {
        "id": 5, "code": "PRJ-005", "name": "Mobile App Revamp", "description": "...",
        "start_date": "2026-06-01", "deadline": "2026-12-15",
        "status": { "code": "ongoing", "label": "Ongoing" },
        "project_head": { "id": 10, "name": "Vikram Shah" }
      },
      "team": {
        "total_members": 6, "active_members": 6,
        "members": [ { "id": 42, "employee_id": "EMP0042", "name": "Asha Rao", "email": "asha.rao@example.com", "role": "employee", "designation": "Software Engineer", "is_head": "0", "is_project_head": false } ]
      },
      "progress": { "total_tasks": 40, "completed_tasks": 22, "percentage": 55 },
      "user_assignment": { "is_assigned": true, "is_project_head": false, "role_in_project": "team_member" }
    }
  ],
  "summary": { "total_projects": 8, "by_status": { "ongoing": 5, "completed": 3 }, "assigned_to_me": 3, "where_i_am_head": 0 },
  "user_role": "employee",
  "viewing_as": { "role": "employee", "can_view_all": false }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Scope is not `company`/`team`/`own` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged server-side via `Log::error`, not leaked) |

**Special behavior / notes:** for `own` scope, member details in the response are trimmed down to `id`/`name`/`designation`/`is_project_head` only (no email/role) — a narrower payload than `company`/`team` scope gets. Members, their job details and designations are loaded in bulk for all projects (fixed query count, not per member).

---

## GET /api/ai/task
**Purpose:** All tasks visible to the caller, grouped into `assigned_by_me` / `assigned_to_me` / `team_tasks` / `others`, each fully expanded with project, assignment, update-history, and approval details.
**Controller:** `App\Http\Controllers\AI\TaskController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `Task`.
**Permission/role required:** gate check is `RbacService::can($authUser, 'tasks', 'view')` (boolean, not scope-based) — visibility filtering below the gate still uses the legacy `role` column switch (`admin`/`hr` see all; `manager` sees created-by-them + team + own; `employee` sees only tasks assigned to them; any other custom role falls back to its real `tasks:view` scope: `team` or non-`company` → limited to assigned-to-self, matching `own`).

**Request parameters:** None read by this controller (no `Request` fields consulted for filtering).

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/task' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Tasks fetched successfully",
  "data": {
    "assigned_by_me": [],
    "assigned_to_me": [
      {
        "id": 301, "task_code": "TSK-0301", "title": "Fix login bug", "description": "...",
        "priority": "high", "status": "in_progress", "task_date": "2026-09-20", "deadline_date": "2026-09-30",
        "project": { "id": 5, "name": "Mobile App Revamp", "code": "PRJ-005" },
        "assigned_by": { "id": 10, "name": "Vikram Shah", "employee_id": "EMP0010", "email": "vikram@example.com", "role": "manager" },
        "assigned_to": { "id": 42, "name": "Asha Rao", "employee_id": "EMP0042", "email": "asha.rao@example.com", "role": "employee" },
        "user_relationship": "assignee", "is_assigned_by_me": false, "is_assigned_to_me": true,
        "file": null, "voice_file": null,
        "deadline_status": "due_soon", "days_remaining": 2, "is_overdue": false,
        "latest_update": { "status": "in_progress", "remarks": "Working on it", "updated_by": "Asha Rao", "updated_at": "2026-09-27T10:00:00.000000Z" },
        "update_history": [ { "id": 900, "status": "in_progress", "remarks": "Working on it", "updated_by": "Asha Rao", "updated_at": "2026-09-27T10:00:00.000000Z" } ],
        "total_updates": 1,
        "approval": null,
        "created_at": "2026-09-19T08:00:00.000000Z", "updated_at": "2026-09-27T10:00:00.000000Z"
      }
    ],
    "team_tasks": [],
    "others": []
  },
  "summary": { "total_tasks": 14, "by_status": { "in_progress": 5, "completed": 9 }, "by_priority": { "high": 3, "medium": 8, "low": 3 }, "overdue": 1, "due_today": 0, "due_soon": 2 }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | `RbacService::can($authUser,'tasks','view')` is false | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

`summary` is computed over the same visibility as `data` (including the custom-role `tasks:view` scope fallback).

**Special behavior / notes:** `file`/`voice_file` are rendered with the bare `asset()` helper (not the tenant-aware `config('app.url')` pattern most other endpoints in this module use) — could produce a different host than the rest of the API's file URLs if `ASSET_URL`/`APP_URL` diverge.

---

## GET /api/ai/expense
**Purpose:** Expense claims visible to the caller (company/team/own scope) with status/requirement-type/amount summaries and an optional per-employee grouping.
**Controller:** `App\Http\Controllers\AI\ExpenseController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `Expense`.
**Permission/role required:** `RbacService::scopeFor($authUser, 'expenses', 'view')` → `company` (no filter) / `team` (team + own) / `own`; any other value → see error table (note the status code below).

**Request parameters:** None read by this controller (no filters applied beyond scope).

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/expense' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Expense data fetched successfully!",
  "data": [
    {
      "id": 77, "expense_number": "EXP-0077", "employee_id": "EMP0042", "employee_name": "Asha Rao", "user_id": 42, "user_role": "employee",
      "employee_profile_image": "https://vpshrms.shurttech.com/uploads/profile/42.jpg",
      "expense_type": "Travel", "expense_type_id": 3, "expense_date": "2026-09-15",
      "project_name": "Mobile App Revamp", "project_code": "PRJ-005", "project_id": 5,
      "requirement_type": "settlement", "amount": "2500.00", "description": "Client visit cab fare",
      "status": "pending", "is_billable": 1, "created_at": "2026-09-15T14:00:00.000000Z",
      "file_url": "https://vpshrms.shurttech.com/uploads/expenses/receipt77.jpg"
    }
  ],
  "summary": {
    "total_expenses": 30, "total_amount": 84500,
    "by_status": { "pending": 4, "approved": 20, "complete": 5, "cancelled": 1 },
    "by_requirement_type": { "advance": 6, "settlement": 24 },
    "total_amount_by_status": { "pending": 9200, "approved": 62000, "complete": 12500, "cancelled": 800 }
  },
  "grouped_by_employee": [
    { "user_id": 42, "employee_id": "EMP0042", "employee_name": "Asha Rao", "total_expenses": 5, "total_amount": 14200, "expenses": [ "...same shape as data[] items..." ] }
  ]
}
```
`grouped_by_employee` is `null` when scope is `own`.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Scope is not `company`/`team`/`own` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

**Special behavior / notes:** `file_url` is a short-lived signed receipt link (`ExpenseAttachmentService::url()`). `amount` values here are read straight off the `expenses` table via Eloquent casts (strings in the sample above reflect typical decimal-column serialization) — unlike the newer `Services/Expense/*` layer, this endpoint does **not** use the `App\Support\Money` integer-paise helper, so don't assume paise-precision semantics from this endpoint specifically.

---

## GET /api/ai/holiday
**Purpose:** The current calendar year's active company holidays.
**Controller:** `App\Http\Controllers\AI\HolidayController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `Holiday`.
**Permission/role required:** none — any authenticated user of any role gets the same list.

**Request parameters:** None (year is always `now()->year`; there is no way to request a different year through this endpoint).

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/holiday' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": [
    { "id": 4, "start_date": "2026-10-20", "end_date": "2026-10-20", "name": "Diwali", "description": "Festival of lights" }
  ]
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occured. Please try again later."}` (sic — "occured" is a typo in the source, preserved here) |

**Special behavior / notes:** only `status = 1` holidays for the current calendar year, ordered by `start_date` ascending. No historical or future-year access.

---

## GET /api/ai/shift-plan
**Purpose:** Role-scoped shift roster (which shift, or week-off, each employee has on each date in range), with per-employee and overall summaries.
**Controller:** `App\Http\Controllers\AI\ShiftController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** resolved via `Auth::user()->tenant_id`, falling back to a `users` table lookup by the target `user_id` inside the private helper — always ultimately tenant-bound.
**Permission/role required:** hardcoded `role` switch — `admin`/`hr` get every active employee's plan, `manager` gets themself + their reporting team (`User::managedBy()`), `employee` gets only their own; any other role value → `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `start_date` | string (`YYYY-MM-DD`) | No | Defaults to the start of the current month. Unparseable → `422`. |
| `end_date` | string (`YYYY-MM-DD`) | No | Defaults to the end of the current month. Unparseable → `422`. Must not be before `start_date` (`400` if it is), and the range must not exceed 6 months (`400` if it does). |

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/shift-plan?start_date=2026-09-01&end_date=2026-09-30' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Shift plan fetched successfully",
  "data": [
    {
      "user_id": 42, "employee_id": "EMP0042", "name": "Asha Rao", "email": "asha.rao@example.com", "role": "employee",
      "designation": "Software Engineer", "department": "Engineering", "joining_date": "2023-01-10",
      "shift_plan": [
        { "date": "2026-09-01", "shift_id": 1, "shift_name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "status": "completed", "type": "Shift", "color_code": "#3b82f6" },
        { "date": "2026-09-06", "shift_id": null, "shift_name": null, "start_time": null, "end_time": null, "status": "Week Off", "type": "Week Off", "color_code": "#dc3545" }
      ],
      "summary": { "total": 30, "shifts": 26, "week_offs": 4, "upcoming": 2, "ongoing": 0, "completed": 24, "missed": 0, "cancelled": 0 },
      "is_current_user": true
    }
  ],
  "meta": { "user_role": "employee", "date_range": { "start": "2026-09-01", "end": "2026-09-30" }, "total_employees": 1 }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 422 | `start_date`/`end_date` not a valid date | `{"success": false, "message": "Invalid date. Use the format YYYY-MM-DD for start_date and end_date."}` |
| 400 | `end_date` before `start_date` | `{"success": false, "message": "End date cannot be before start date"}` |
| 400 | Range exceeds 6 months | `{"success": false, "message": "Date range cannot exceed 6 months"}` |
| 401 | Not authenticated (defensive check; unreachable in practice since `auth:api` already ran) | `{"success": false, "message": "User not authenticated"}` |
| 403 | Role is not `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access"}` |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to fetch shift plan"}` (logged) |

**Special behavior / notes:** multi-shift (2026-10-02): each date item is the main shift and carries `additional_shifts` (the day's 2nd+ shifts, same item shape). If a tenant has `custom_shifts_enabled = false` (checked via `TenantShiftResolver::isCustomShifts()`), every working day in range gets the tenant's single default shift instead of per-user `UserShift` rows — day-based week-off weekdays are still excluded. Users whose `joining_date` is inside the requested range have their effective start date pushed forward to their joining date; a user who joined after the entire requested range is silently omitted from `data`.

---

## GET /api/ai/attendance-regularization
**Purpose:** Attendance regularization (correction) requests visible to the caller, with approver details.
**Controller:** `App\Http\Controllers\AI\AttendanceRegularizationController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** explicit `where('ar.tenant_id', $authUser->tenant_id)` — note the query starts from `AttendanceRegularization::withoutGlobalScopes()`, i.e. the model's own tenant global scope is deliberately bypassed and re-applied manually via this raw filter.
**Permission/role required:** hardcoded `role` switch — `admin`/`hr` see all (optionally filtered to one `user_id`/`employee_id`), `manager` sees team + own, `employee` sees only their own.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `user_id` | integer | No | Admin/HR only. |
| `employee_id` | string | No | Admin/HR only; matches `users.employee_id`. |
| `status` | string | No | Any non-empty value other than `all` filters `ar.status`. |
| `request_type` | string | No | Any non-empty value other than `all` filters `ar.request_type`. |
| `start_date` | string (date) | No | `ar.date >= start_date`. |
| `end_date` | string (date) | No | `ar.date <= end_date`. |

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/attendance-regularization?status=PENDING' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Regularization requests fetched successfully",
  "data": [
    {
      "id": 55, "date": "2026-09-22", "request_type": "missed_punch", "in_time": "09:15:00", "out_time": "18:05:00",
      "reason": "Forgot to clock in", "file": "https://vpshrms.shurttech.com/uploads/regularization/55.jpg", "file_url": "https://vpshrms.shurttech.com/uploads/regularization/55.jpg", "status": "PENDING", "user_id": 42, "approved_by": null, "approved_date": null,
      "created_at": "2026-09-22T19:00:00.000000Z",
      "employee_id": "EMP0042", "user_name": "Asha Rao", "user_email": "asha.rao@example.com", "user_role": "employee",
      "designation": "Software Engineer", "profile_image": "https://vpshrms.shurttech.com/uploads/profile/42.jpg",
      "approved_by_name": null, "approved_by_employee_id": null
    }
  ]
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Role is not `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

**Special behavior / notes:** `file`/`file_url` are full URLs via `file_url($path, 'regularization')` (null when no attachment); `profile_image` via `file_url(..., 'profile_photo')`. none of `status`/`request_type` filters validate against an allowed-value list (`in:` rule) — any string is passed straight into the `WHERE` clause as a literal equality check, so a typo'd filter value just returns zero rows rather than a validation error.

---

## GET /api/ai/team
**Purpose:** Full HR profile (same shape as `/api/ai/my-profile`) for every teammate visible to the caller — self for `employee`, self+reports for `manager`, all non-admin active users for `admin`/`hr`.
**Controller:** `App\Http\Controllers\AI\TeamController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `User`.
**Permission/role required:** hardcoded `role` switch — `admin`/`hr` → all active users except other `admin`s; `manager` → `User::managedBy($authUser->id)` plus self; `employee` → self only; any other role → `403`.

**Request parameters:** None read by this controller.

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/team' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:** `data` is an array of the same per-user object shape documented under `GET /api/ai/my-profile` (`id`, `employee_id`, `name`, `personal_details`, `bank_details`, `job_details` — here additionally including `office_radius`/`office_description`/`office_latitude`/`office_longitude` from the assigned attendance location — `location`, `current_payroll`, `payroll_history`, `documents`).

**Privacy:** Aadhaar, PAN, bank account number, UAN, PF and ESI are returned in full only for the caller's own record, or when `RbacService::scopeFor($user, 'payroll', 'view') === 'company'` (admin/HR). For anyone else (e.g. a manager viewing reportees) they are masked to the last 4 characters (`"••••0640"`), `current_payroll` is `null` and `payroll_history` is `[]`. The envelope:
```json
{
  "success": true,
  "message": "Team data fetched successfully",
  "data": [ { "...": "per-user profile object, see above" } ],
  "summary": { "total": 6, "by_role": { "employee": 5, "manager": 1 }, "current_user": { "id": 10, "name": "Vikram Shah", "role": "manager" } },
  "user_role": "manager"
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Role is not `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged) |

---

## GET /api/ai/leave
**Purpose:** Leave requests plus computed leave-balance summaries (credited/used/pending/available per leave type) for every user in the caller's scope, for a given year.
**Controller:** `App\Http\Controllers\AI\LeaveController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** implicit via `TenantTrait` global scope on `Leave`/`LeaveType`/`LeaveBalance`/`LeaveTransaction`/`User`.
**Permission/role required:** `RbacService::scopeFor($authUser, 'leave', 'view')` → `company` (no filter) / `team` (team + own) / `own`; any other value → `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `from_date` | string (date) | No | Requires `to_date` to be present too — both must be set for the range filter to apply (`whereBetween('leaves.start_date', ...)`). |
| `to_date` | string (date) | No | See above. |
| `status` | string | No | Exact match on `leaves.status`. |
| `user_id` | integer | No | Exact match on `leaves.user_id` (still bounded by scope). |
| `leave_type` | integer | No | Exact match on `leaves.leave_type`. |
| `year` | integer | No | Defaults to the current calendar year; also used to scope the balance/transaction aggregates, independent of `from_date`/`to_date`. |

All filters apply only when the value is non-empty (`?status=` is the same as no `status`).

**File uploads:** None

**Sample request:**
```bash
curl -X GET 'https://vpshrms.shurttech.com/api/ai/leave?year=2026&status=approved' \
  -H 'Authorization: Bearer <jwt>' -H 'Device-Token: <device-token>'
```

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Leave data with balances fetched successfully",
  "data": [
    {
      "employee": { "id": 42, "employee_id": "EMP0042", "name": "Asha Rao", "email": "asha.rao@example.com", "role": "employee" },
      "balance_summary": { "current_balance": 12.0, "total_credited": 18.0, "total_used": 6.0, "total_pending": 1.0, "total_available": 12.0, "year": 2026 },
      "balance_details": [
        { "leave_type_id": 1, "leave_type": "Casual Leave", "credit_type": "monthly", "annual_quota": 12.0, "credited": 9.0, "used": 3.0, "pending": 0.0, "available": 6.0, "balance": 6.0 }
      ],
      "leaves": [
        {
          "id": 210, "leave_id": "LV-0210", "leave_type": "Casual Leave",
          "period": { "start_date": "2026-09-15", "end_date": "2026-09-16", "start_session": "fullday", "end_session": "fullday", "total_days": 2, "leave_count": 2 },
          "reason": "Personal work", "status": "approved", "file_url": null, "submitted_at": "2026-09-10T09:00:00.000000Z", "payment_status": "paid"
        }
      ],
      "total_leaves": 1
    }
  ],
  "summary": { "total_employees": 1, "total_leave_requests": 1, "total_pending_requests": 0, "total_approved_requests": 1, "total_cancelled_requests": 0, "total_days_requested": 2, "year": 2026 },
  "user_role": "employee",
  "filters_applied": { "year": 2026, "from_date": null, "to_date": null, "status": "approved", "user_id": null }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Scope is not `company`/`team`/`own` | `{"success": false, "message": "Unauthorized access. Invalid role."}` |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` (logged server-side via `Log::error`, not leaked) |

**Special behavior / notes:** when the caller's leave-request query returns zero rows but the scope is `company`/`team`/`own`, the controller still falls back to listing **all users in that scope** (not just ones with leave activity) so it can report their balances with an empty `leaves` array — i.e. `data` is really "users in scope with their balances," not "leave requests," despite the endpoint name.

---

# Endpoints added 2026-10-05

Nine more `view_ai_all()` controllers. They share `App\Http\Controllers\AI\Concerns\AiScope`:
`scopeOf($user, $module)` = `RbacService::scopeFor($user, $module, 'view')`;
`visibleUserIds()` → `null` (company: everyone) / caller + `user_reporting_heads` reportees (team) / caller (own);
an optional `user_id` query param narrows that set (a user outside it returns no rows, not an error).
Every one returns `"scope": "own|team|company"` next to `data` / `summary`, a `403
{"success": false, "message": "Unauthorized access. Invalid role."}` when the caller has no view grant
(except where noted), and a logged generic `500 {"success": false, "message": "An error occurred. Please try again later."}`.
All queries filter `tenant_id` explicitly; none is paginated. Same headers as above.

| Endpoint | Plan feature | RBAC module (view) |
|---|---|---|
| `GET /api/ai/asset` | `asset_management` | `assets` |
| `GET /api/ai/loan` | `loan_management` | `loans` |
| `GET /api/ai/performance` | `kpi_performance` | `performance` |
| `GET /api/ai/policy` | — (always) | `employee` (only to read another employee) |
| `GET /api/ai/offboarding` | `offboarding` | `offboarding` |
| `GET /api/ai/meeting` | `meetings` | `meetings` |
| `GET /api/ai/daily-report` | `wfh_travel` | `requests` |
| `GET /api/ai/onboarding` | `onboarding` | `onboarding` (no grant → own involvement only, no 403) |
| `GET /api/ai/recruitment` | `recruitment` | `recruitment` (no 403 — see below) |
| `GET /api/ai/overtime` (2026-10-06) | `overtime` | `overtime` |
| `GET /api/ai/payroll` (2026-10-06) | `payroll` | `payroll` (own = processed / paid only) |
| `GET /api/ai/approvals` (2026-10-06) | — (per module inside) | each module's **approve** grant (no 403) |
| `GET /api/ai/leave-history` (2026-10-06) | `leave_management` | `leave` |

## GET /api/ai/asset
**Controller:** `AI\AssetController`. **Visibility:** own = assets whose `current_assignee_id` is the caller; team = assigned to the caller or a reportee; company = every asset (unassigned included).
**Query:** `status` (`available|pending_acceptance|assigned|in_repair|damaged|lost|retired|disposed`), `category_id`, `search` (name / code / serial / brand), `user_id`.
**`data[]`:** `id, asset_code, name, category, type, brand, model_number, serial_number, status, condition, branch, location_notes, image_url, assigned_to{id,name,employee_id}, assignment{status,assigned_at,accepted_at,expected_return_date}` (current `pending_acceptance`/`accepted` row), `warranty{end_date,provider,status: active|expiring_soon (≤30 days)|expired|null}`, `open_repair{status,issue,reported_at,expected_return_date}`; company scope also `purchase_date, purchase_cost, vendor`.
**`summary`:** `total_assets, by_status, by_category, assigned, pending_acceptance, in_repair, warranty_expiring_30_days, warranty_expired, assigned_to_me` (+ `total_purchase_cost` at company scope).

## GET /api/ai/loan
**Controller:** `AI\LoanController`. **Query:** `status` (`pending|approved|active|closed|default|cancelled`), `user_id`.
**`data[]`:** `id, loan_number, employee{id,name,employee_id}, category` (`loan_categories` via `loans.loan_type_id`), `purpose, status, amount, processing_fee, interest_rate, tenure_months, total_payable, repayment_type, emi_amount, amount_repaid` (Σ `loan_repayments.paid_amount`), `outstanding` (`remaining_amount`, else total − repaid), `installments{total,paid,pending,overdue,overdue_amount}`, `next_installment{number,due_date,amount,status,auto_deducted_from_salary}`, `dates{applied,loan_date,approved_at,disbursed_at,first_emi_date,last_emi_date,lumpsum_due_date,closed_date}`, `rejection_reason, cancellation_reason`.
**`summary`:** `total_loans, by_status, running_loans` (approved + active), `total_borrowed, total_outstanding, monthly_emi_total, overdue_installments, pending_approval, salary_advances_outstanding`.
Each item also has `kind` (`loan` | `salary_advance`) and `advance_month` (salary advance: the `YYYY-MM` payroll it is deducted from — added 2026-10-06).

## GET /api/ai/performance
**Controller:** `AI\PerformanceController`. **Query:** `month` (`YYYY-MM`; default = latest `employee_kpi_scores.reporting_month` among visible employees; anything else → `422 {"success": false, "message": "Invalid month. Use the format YYYY-MM."}`), `user_id`.
**`data[]`** (sorted by score, best first): `employee, month, overall_score, grade, status, component_scores{attendance,task_completion,deadline_met,regularization,project_participation,manager_rating}, attendance{present_days,absent_days,half_days,late_days,early_departure_days,paid_leaves,unpaid_leaves,working_days,total_late_minutes,overtime_hours}, tasks{assigned,completed,on_time,late,overdue}, regularizations{total,approved,rejected,pending}, manager_feedback, manager_review` (`manager_performance_reviews` for the month — only once `submitted`/`acknowledged`, or to the reviewer), `trend[]` (up to 6 months ending at `month`), `current_month_so_far{average_daily_score,days_scored,late_days,absent_days}` (from `employee_daily_performance`, calculated working days of the current month; null when none).
**`summary`:** `month, employees, average_score, by_grade`; team/company scope also `top_5`, `bottom_5`.

## GET /api/ai/policy
**Controller:** `AI\PolicyController`. The rules one employee follows today — company values with that employee's Employee 360 overrides on top. **Query:** `user_id` (default the caller; another employee needs `employee:view` covering them, else `403`; unknown id → `403`).
**`data`:** `employee, effective_on, attendance` (all `AttendancePolicySnapshot` fields from `PolicyResolver::forUserDate()`, camelCase: grace mode/minutes, ratios, late/early allowances, actions and deductions, overtime-after hours, sandwich leave …), `leave_types[]{id,name,code,available_to_employee,credit_type,credit_value,is_unpaid,min_notice_days,max_consecutive_days,requires_document_after_days,max_carry_forward,carry_forward_period,carry_forward_expiry_months,is_encashable,description,customised}` (via `EmployeePolicyService::leaveRule()`; `max_carry_forward` = days carried into the next `carry_forward_period` (`month`/`week`/`leave_year`, null when not credited), null = no limit, 0 = none carry; expiry null = never, yearly types only), `leave_year{current_start,next_start,carry_forward_enabled}` (`LeaveYearService`; when `carry_forward_enabled` is false nothing lapses), `overtime{enabled,mode,auto_start_basis,auto_start_after_minutes,eligible,min_hours,rate_type,fixed_rate_per_hour,rate_multiplier,max_hours_per_day,max_hours_per_month,require_approval,auto_approve_limit,overtime_after_hours}` (null when the `overtime` feature is off; `mode` request|auto and the rest from Company Policies → Overtime via `OvertimePolicyService`; `overtime_after_hours` is the reports-only attendance value), `performance` (`PerformancePolicySnapshot::toArray()`; null when `kpi_performance` is off), `limits{wfh_max_days_per_month,wfh_min_notice_days,regularization_max_per_month,regularization_max_days_back,expense_monthly_limit}` (0 = no limit; `RequestLimitService::forEmployee()`), `week_offs{employee[],company_default_days,week_start}`, `shifts{mode,list[]}`, `notice_period_days` (`tenants.notice_period`), `customised` (section => keys this employee has custom values for).

## GET /api/ai/offboarding
**Controller:** `AI\OffboardingController`. **Visibility** on `offboarding_requests.employee_id`. **Query:** `status` (`pending_approval|approved|rejected|completed|cancelled`), `user_id`.
**`data[]`:** `id, request_code, employee{id,name,employee_id,department,designation}, reason, reason_detail, status, current_stage, dates{request_date,resignation_date,last_working_date,original_last_working_date,days_until_last_working_day,completed_at}, notice_period_days_required, manager_review{status,by,at,comments}, hr_review{…}, knowledge_transfer, clearance{status,asset_return,document_return,total,completed,pending_items[{category,label}]}, exit_interview{status,skipped,date,interviewer}, settlement{status,computed_total,final_total,paid_date}, eligible_for_rehire, rejected_reason, cancelled_reason`.
**`summary`:** `total_requests, by_status, by_stage, pending_approval, leaving_this_month, pending_clearance_items`.

## GET /api/ai/meeting
**Controller:** `AI\MeetingController`. **Visibility:** same rule as the mobile `/meetings` list — company scope sees every meeting, anyone else only meetings they created or are a participant of. **Query:** `start_date`, `end_date` (`Y-m-d`; default today → +30 days; swapped if reversed), `status` (`scheduled|ongoing|completed|cancelled|postponed|all`).
**`data[]`:** `id, meeting_code, title, description, agenda, date, start_time, end_time, duration_minutes, type, mode, location, meeting_link, status, cancellation_reason, recurrence, organiser, my_role, my_response, participants[{id,name,employee_id,role,response,is_mom_writer}], responses` (count per response), `minutes_status, decisions`.
**`summary`:** `total_meetings, today` (not cancelled), `by_status, awaiting_my_response, minutes_pending` (completed, minutes not finalized), `date_range`.

## GET /api/ai/daily-report
**Controller:** `AI\DailyReportController`. The `daily_reports` rows employees file per day of an approved WFH / travel request (the `daily_reports` *plan feature* is the Reports section and is not used here). **Query:** `start_date`, `end_date` (default 1st of month → today), `status` (`DRAFT|SUBMITTED`), `user_id`.
**`data[]`:** `id, date, employee, request{id,type,start_date,end_date}, work_done, challenges_faced, next_day_plan, start_time, end_time, total_hours, status, manager_comments, submitted_at`.
**`missing_reports[]`:** `{date, employee, request{id,type}}` — every day (up to today) of an APPROVED request in range with no SUBMITTED report. Counts every calendar day of the request, week-offs included.
**`summary`:** `total_reports, submitted, drafts, total_hours, missing_reports, employees_with_missing_reports, date_range`.

## GET /api/ai/onboarding
**Controller:** `AI\OnboardingController`. **Visibility:** company scope = every onboarding; otherwise the caller's own onboarding, their reportees', ones where they are the `onboarding_buddy`, and ones with a checklist item `assigned_to` them. With no `onboarding:view` grant at all the same own-involvement rule applies (a new joiner sees their own checklist) instead of a 403. **Query:** `status` (`not_started|in_progress|completed|cancelled`).
**`data[]`:** `id, assignment_code, new_joiner{user_id,name,employee_id,candidate_code,designation,department}, status, joining_date, start_date, expected_completion_date, actual_completion_date, buddy, progress{total_tasks,completed,overdue,percentage}, tasks[{task,category,mandatory,owner,owner_role,due_date,status,is_overdue,assigned_to_me,completed_by,completed_at,remarks}], notes`. `onboarding_tasks` templates may be global (`tenant_id` NULL), so that join is not tenant-filtered.
**`summary`:** `total_onboardings, by_status, in_progress, overdue_tasks, my_pending_tasks`.

## GET /api/ai/recruitment
**Controller:** `AI\RecruitmentController`. **Visibility:** company scope (`recruitment:view` = company) sees all openings, interviews and offers (incl. salary ranges and offered CTC); anyone else gets openings where they are `hiring_lead` and interviews where they are the interviewer or in `co_interviewer_ids` — `offers` is `null` for them. Never a 403 (empty lists when not involved). Candidate contact details (email / phone / CTC) are never returned. **Query:** `status` (opening: `draft|published|closed|on_hold`), `job_opening_id`, `start_date` / `end_date` for interviews (default today → +14 days).
**`data.openings[]`:** `id, job_code, title, department, designation, employment_type, experience_required, skills_required, location, status, published_date, closed_date, hiring_lead, vacancies, positions_filled, applications{total,active,by_stage,by_source}` (+ `salary_range` at company scope).
**`data.interviews[]`:** `id, interview_code, date, time, duration_minutes, round, type, meeting_link, location, candidate{name,candidate_code,total_experience,current_company}, job{title,job_code}, interviewer, co_interviewers[], is_my_interview, status, outcome, rating`.
**`data.offers[]`** (company only): `id, offer_code, candidate, designation, department, offer_date, joining_date, status, offered_ctc, acceptance_date, rejection_reason`.
**`summary`:** `openings, openings_by_status, open_vacancies` (published: vacancies − filled), `total_applications, interviews_in_range, interviews_today, my_interviews, offers_by_status, interview_date_range`.

## GET /api/ai/overtime
**Controller:** `AI\OvertimeController` (added 2026-10-06). **Feature:** `overtime`. **Visibility:** `overtime:view` — own / team / company.
**Query:** `month` (`YYYY-MM`, default current month; anything else → `422 {"success": false, "message": "month must be YYYY-MM."}`), or `from` + `to` (`Y-m-d`, both needed), `status` (`pending|approved|rejected`), `source` (`request|auto`), `user_id`.
**`data[]`:** `id, employee{id,name,employee_id}, date, source` (`auto` = calculated from attendance, Company Policies → Overtime), `status, requested_hours, approved_hours` (approved only), `auto_minutes, adjusted_by_hr, raised_by_admin, reason, rejection_reason, approved_by, approved_at, created_at`.
**`per_employee[]`:** `employee, approved_hours, pending_hours, auto_hours, monthly_limit, remaining_this_month` (limit fields only when the period is one month; employee override, else company; null = no limit).
**`summary`:** `period{from,to}, total_entries, by_status, by_source, approved_hours, pending_hours, rejected_hours`. **`settings`:** `enabled, mode` (`request|auto`), `auto_start_basis, auto_start_after_minutes, min_hours, max_hours_per_day, max_hours_per_month, rate_type, rate_multiplier, fixed_rate_per_hour`.

## GET /api/ai/payroll
**Controller:** `AI\PayrollController` (added 2026-10-06). **Feature:** `payroll`. **Visibility:** `payroll:view` — own (the caller's **processed / paid** payslips only; drafts hidden), team, company (all statuses).
**Query:** `month` or `from_month` / `to_month` (`YYYY-MM`; default the last 6 months; bad format → 422 `"<param> must be YYYY-MM."`), `payment_status` (`pending|processed|paid|cancelled`), `user_id`.
**`data[]`:** `id, employee, month, engine` (`dynamic_v1` | legacy), `payment{status,date,mode,reference}, days{working_days,payable_days,present,half_days,absent,paid_leave,unpaid_leave,holidays,week_offs}, overtime{hours,rate_per_hour,amount}, earnings{basic,hra,conveyance,medical,children,post,lta,incentive,special,overtime}, deductions{pf,esi,professional_tax,tds,loan_emi,salary_advance,late,early_leaving,other}, employer_contributions{pf,esi}, gross_earnings, total_deductions, net_pay, lines[{name,type,amount,taxable}]` (every `payroll_components` row — dynamic components, bonuses, arrears, reimbursements), `remarks, processed_at`.
**`summary`:** `period, payslips, employees, by_payment_status, total_gross, total_deductions, total_net_pay, total_overtime_amount, total_loan_and_advance_recovered, by_month{YYYY-MM:{payslips,gross,net_pay}}`.

## GET /api/ai/approvals
**Controller:** `AI\ApprovalController` (added 2026-10-06). **Feature:** none on the route — each module is included only when its own feature is on and the caller has that module's **`approve`** permission with team or company scope (rbac modules: `leave`, `attendance` for regularization, `requests`, `overtime`, `expenses`, `loans`, `offboarding`). The caller's own requests are never listed. An employee gets an empty result (not 403).
**Query:** `module` (`leave|regularization|wfh_travel|overtime|expense|loan|offboarding`; anything else → `422 {"success": false, "message": "Unknown module."}`), `user_id`.
**`data`:** object keyed by module, each a list of pending items with `id, employee{id,name,employee_id}, applied_at` plus: leave `leave_type, from, to, days, sessions, reason`; regularization `date, type, in_time, out_time, reason`; wfh_travel `type, from, to, reason`; overtime `date, hours, reason`; expense `expense_number, type, amount, date, description, possible_duplicate`; loan `loan_number, kind, advance_month, category, amount, tenure_months, emi_amount, purpose`; offboarding `request_code, resignation_date, last_working_date, reason, stage`.
**`summary`:** `total_pending, by_module{<module>:{count,scope}}`.

## GET /api/ai/leave-history
**Controller:** `AI\LeaveHistoryController` (added 2026-10-06). **Feature:** `leave_management`. **Visibility:** `leave:view` — own / team / company.
**Query:** `from` / `to` (`Y-m-d`; default current leave-year start → today; invalid → default), `leave_type_id`, `user_id`.
**`data[]`** (newest first, max 2000): `id, employee, leave_type{id,name}, kind` (`credit|leave_taken|leave_revoked|carry_forward_lapse|carry_forward_expiry|adjustment`), `direction` (`add|sub`), `days, balance_before, balance_after, date, leave_id, paid, remarks`.
**`carry_forward[]`:** `employee, leave_type, period_start, period` (`month|week|leave_year`), `closing_balance, limit, carried, lapsed, expires_on, expired`.
**`summary`:** `period, entries, by_kind{kind:{entries,days}}, credited_days, taken_days` (taken − revoked), `lapsed_days, carry_forward_enabled, truncated`.
