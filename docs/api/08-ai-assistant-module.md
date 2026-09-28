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

**Permissions:** each endpoint independently re-derives a view "scope" (`own` / `team` / `company`) — either via `RbacService::scopeFor($user, module, 'view')` (newer controllers) or a hardcoded `switch ($authUser->role)` (older controllers still using the plain `role` column: `admin`/`hr`/`manager`/`employee`). See the per-endpoint table for which mechanism and which module key is used, and note that **the "unauthorized" status code is inconsistent across controllers** — some return `403`, some return `200` with `success: false` (documented per-endpoint below; do not assume 403 uniformly across this module).

**Pagination:** none, except `GET /api/ai/request` (`per_page` triggers Laravel pagination; omit it to get the full unpaginated set).

**File uploads:** none on any endpoint in this module (all `GET`).

**Common error shape when uncaught exceptions occur:** `{"success": false, "message": "..."}`, HTTP `500` — except `GET /api/ai/team`, which has a debug `dd($e->getMessage())` left in its `catch` block (see that endpoint's notes — this means an exception there does **not** return JSON at all, it dumps and halts).

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
| `date` | string (`Y-m-d`) | No | Single day. Defaults to today **only if none of `date`/`start_date`/`end_date` are given**. |
| `start_date` | string (`Y-m-d`) | No | Combined with `end_date` for a range (`whereBetween`). |
| `end_date` | string (`Y-m-d`) | No | See above. |
| `department_id` | integer | No | Filter users by department. |

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
| 500 | Unhandled exception | `{"success": false, "message": "Error: <exception message>"}` |

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
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later.<exception message>"}` (note: no separator between the fixed text and the exception message) |

**Special behavior / notes:** the controller also computes `summary` (totals by status/request-type) and `grouped_by_employee` (when scope isn't `own`) internally, but **both are currently commented out of the returned JSON** — only `data` (and `pagination` when applicable) is actually returned despite the code computing more.

---

## GET /api/ai/attendance
**Purpose:** Day-by-day attendance matrix (present/absent/week-off/checked-in-only) for a date range, with per-status and per-user summary counts.
**Controller:** `App\Http\Controllers\AI\AttendanceController::view_ai_all`
**Auth:** JWT bearer + Device-Token
**Tenant scope:** `$authUser->tenant_id ?? Session::get('tenant_id')`; `400` if unresolved.
**Permission/role required:** `RbacService::scopeFor($authUser, 'attendance', 'view')` → `own`/`team`/`company`; `null` → `403`.

**Request parameters (query string):**
| Field | Type | Required | Notes |
|---|---|---|---|
| `start_date` | string (`Y-m-d`) | No | Defaults to the 1st of the current month. Invalid/malformed values silently fall back to the default (no validation error). |
| `end_date` | string (`Y-m-d`) | No | Defaults to today. Swapped automatically if after `start_date`... wait, if `start_date > end_date` they are swapped. Range is capped to 366 days (end date is clamped). |
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
      "status_category": "present"
    }
  ],
  "summary": {
    "total_records": 28,
    "by_status": { "present": 20, "absent": 3, "weekoff": 5, "checked_in_only": 0 },
    "by_user": [
      { "name": "Asha Rao", "total": 28, "present": 20, "absent": 3, "weekoff": 5, "checked_in_only": 0 }
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
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later.", "error": "<exception message>"}` — note this endpoint **does** leak the raw exception message in `error` regardless of `app.debug`. |

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
| 200 | `$authUser->role` matches none of `admin`/`hr`/`manager`/`employee` (practically unreachable — these are the only values the `role` column takes) | `{"success": false, "message": "Unauthorized access. Invalid role."}` — **note the 200 status despite failure**, consistent with the legacy API's "always 200, check `success`" convention. |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later. <exception message>"}` |

**Special behavior / notes:** `user_acknowledged` is hardcoded `false` for every row — the controller has a comment noting acknowledgment-tracking isn't wired up here (unlike the "real" `POST /acknowledge-announcement/{id}` endpoint elsewhere in the API). Only `status = 1` (active) announcements are returned; there is no way to fetch inactive/archived ones through this endpoint.

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

**Special behavior / notes:** for `own` scope, member details in the response are trimmed down to `id`/`name`/`designation`/`is_project_head` only (no email/role) — a narrower payload than `company`/`team` scope gets.

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
| 200 | `RbacService::can($authUser,'tasks','view')` is false | `{"success": false, "message": "Unauthorized access. Invalid role."}` — **200 status despite failure**, unlike most other endpoints in this module which use 403 for the equivalent case. |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later.<exception message>"}` (no separator, same pattern as `/api/ai/request`) |

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
| 200 | Scope is not `company`/`team`/`own` | `{"success": false, "message": "Unauthorized access. Invalid role."}` — **200 status despite failure.** |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Special behavior / notes:** `amount` values here are read straight off the `expenses` table via Eloquent casts (strings in the sample above reflect typical decimal-column serialization) — unlike the newer `Services/Expense/*` layer, this endpoint does **not** use the `App\Support\Money` integer-paise helper, so don't assume paise-precision semantics from this endpoint specifically.

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
| `start_date` | string (date, `Carbon::parse`-able) | No | Defaults to the start of the current month. |
| `end_date` | string (date, `Carbon::parse`-able) | No | Defaults to the end of the current month. Must not be before `start_date` (`400` if it is), and the range must not exceed 6 months (`400` if it does). |

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
| 400 | `end_date` before `start_date` | `{"success": false, "message": "End date cannot be before start date"}` |
| 400 | Range exceeds 6 months | `{"success": false, "message": "Date range cannot exceed 6 months"}` |
| 401 | Not authenticated (defensive check; unreachable in practice since `auth:api` already ran) | `{"success": false, "message": "User not authenticated"}` |
| 403 | Role is not `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access"}` |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to fetch shift plan"}`, plus an `"error"` field with the real exception message **only when `app.debug` is true** |

**Special behavior / notes:** if a tenant has `custom_shifts_enabled = false` (checked via `TenantShiftResolver::isCustomShifts()`), every working day in range gets the tenant's single default shift instead of per-user `UserShift` rows — day-based week-off weekdays are still excluded. Users whose `joining_date` is inside the requested range have their effective start date pushed forward to their joining date; a user who joined after the entire requested range is silently omitted from `data`.

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
| `status` | string | No | Any value other than the literal string `all` filters `ar.status`. |
| `request_type` | string | No | Any value other than the literal string `all` filters `ar.request_type`. |
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
      "reason": "Forgot to clock in", "file": null, "status": "PENDING", "user_id": 42, "approved_by": null, "approved_date": null,
      "created_at": "2026-09-22T19:00:00.000000Z",
      "employee_id": "EMP0042", "user_name": "Asha Rao", "user_email": "asha.rao@example.com", "user_role": "employee",
      "designation": "Software Engineer", "profile_image": "/uploads/profile/42.jpg",
      "approved_by_name": null, "approved_by_employee_id": null
    }
  ]
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Role is not `admin`/`hr`/`manager`/`employee` | `{"success": false, "message": "Unauthorized access. Invalid role."}` — **200 status despite failure.** |
| 401 | Auth/device-token failure | see shared conventions |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred: <exception message>"}` |

**Special behavior / notes:** none of `status`/`request_type` filters validate against an allowed-value list (`in:` rule) — any string is passed straight into the `WHERE` clause as a literal equality check, so a typo'd filter value just returns zero rows rather than a validation error.

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

**Success response — `200`:** `data` is an array of the same per-user object shape documented under `GET /api/ai/my-profile` (`id`, `employee_id`, `name`, `personal_details`, `bank_details`, `job_details` — here additionally including `office_radius`/`office_description`/`office_latitude`/`office_longitude` from the assigned attendance location — `location`, `current_payroll`, `payroll_history`, `documents`), plus:
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
| 500 | **Does not return JSON** — see notes | N/A |

**Special behavior / notes:** ⚠️ this controller's `catch` block calls `dd($e->getMessage())` before the (dead-code) `return response()->json([...], 500)` — `dd()` halts execution and dumps an HTML/plain-text debug page instead of returning JSON. Any exception here will break a JSON-parsing client rather than surface the documented `500` shape; this looks like a debugging leftover rather than intended behavior, worth fixing if this endpoint is actually used in production.

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
