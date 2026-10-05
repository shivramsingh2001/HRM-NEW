# Employee Self-Service API

This module covers the mobile/legacy JSON API's core self-service surface: viewing company holidays, viewing/updating the authenticated employee's own profile, browsing the company directory and team hierarchy, applying for and reviewing leave (including manager/HR approval), and viewing/acknowledging announcements. All endpoints live in `routes/api.php` under the `tenant` middleware group and require an authenticated JWT session bound to a single device.

All sample URLs use the base `https://vpshrms.shurttech.com`.

## Common conventions (apply to every endpoint below unless noted)

- **Auth**: every route requires `Authorization: Bearer <jwt>` (issued by `php-open-source-saver/jwt-auth`) **and** a `Device-Token` header that must match the value stored on `users.last_login_token` (enforced by the `singleLogin` middleware, `App\Http\Middleware\CheckSingleDeviceLogin` or equivalent).
- **Tenant scope**: there is no client-supplied tenant header on these routes. `TenantMiddleware` resolves the tenant purely from the authenticated JWT user's own `tenant_id`, and every Eloquent model using `App\Traits\TenantTrait` is automatically filtered to `tenant_id = current_tenant->id`.
- **Response envelope**: this is the legacy/mobile API — there is **no fixed envelope**. Most actions in this module return `{"success": <bool>, "message": "...", "data": ...}` with HTTP 200 even for many validation/business-rule failures, but this is not universal (e.g. `UserController::team`/`getUserProfile` return a `status` key instead of `success` in places, and several validation failures return `200` rather than `422`). Each endpoint's exact shape is documented individually below — read it, don't assume.
- **Errors common to all routes**:

| HTTP Status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid/expired/blacklisted JWT | `{"message": "Unauthenticated."}` |
| 401 | `Device-Token` header missing or does not match `users.last_login_token` | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |
| 403 | `permission:{module},{action}` middleware check fails (route-level, JSON request) | `{"message": "You do not have permission for this action."}` |

---

## GET /holiday

**Purpose:** List all active holidays for the current calendar year.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Holiday` model is tenant-scoped via `TenantTrait`; only the current tenant's holidays are returned.

**Permissions/role requirements:** None (any authenticated user).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |
| Accept | Recommended | `application/json` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/holiday" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -H "Accept: application/json"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": [
    {
      "id": 4,
      "start_date": "2026-01-26",
      "end_date": "2026-01-26",
      "name": "Republic Day",
      "description": "National holiday"
    }
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception during the query | `{"success": false, "message": "An error occured. Please try again later.<exception message>"}` |

**Pagination:** None — returns the full year's list in one array.

**Filters:** None (hardcoded to `whereYear('start_date', now()->year)` and `status = '1'`); not query-parameterized.

**Special behavior / notes:** Only holidays with `status = '1'` and a `start_date` in the current server year are returned, ordered by `start_date` ascending. Fields returned are limited to `id, start_date, end_date, name, description`.

---

## GET /user/celebrations/today

**Purpose:** Today's birthdays and work anniversaries in the employee's company — for a "Wish them today" card in the app. Same list as the admin web dashboard's "Today's celebrations" card (`App\Services\CelebrationService::today()`).

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `User` is tenant-scoped via `TenantTrait`; only the caller's company.

**Permissions/role requirements:** None (any authenticated user). Not plan-gated.

**Headers:** `Authorization: Bearer <jwt>`, `Device-Token`, `Accept: application/json`.

**Request parameters / body:** None (always "today" in the server timezone).

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/user/celebrations/today" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -H "Accept: application/json"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Today's celebrations fetched successfully.",
  "data": {
    "date": "2026-11-17",
    "birthdays": [
      { "id": 1, "name": "shivram singh", "employee_id": "EMP001", "designation": "Flutter Developer",
        "department": "IT Department", "profile_image": "https://…/profile.jpg" }
    ],
    "anniversaries": [
      { "id": 12, "name": "Rahul", "employee_id": "SH558121", "designation": "HR",
        "department": "HR Department", "profile_image": null, "years": 2 }
    ],
    "total": 2
  }
}
```

With nobody to celebrate: `200` with `"message": "No birthdays or work anniversaries today."`, empty arrays and `"total": 0` — the app should hide the card when `total` is 0.

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Unexpected exception | `{"success": false, "message": "Unable to fetch today's celebrations. Please try again later."}` |

**Special behavior / notes:**
- Active (`status = 1`), non-admin employees only, sorted by name.
- Birthday = month/day of `user_basic_details.dob`; someone born on **29 Feb** is listed on **28 Feb** in non-leap years. Age is deliberately not returned.
- Work anniversary = month/day of `user_job_details.joining_date`, only from the first full year (`years` ≥ 1).
- `profile_image` is a full URL (via `file_url()`), or `null`.

---

## GET /fetch-all-user

**Purpose:** Fetch the company directory (all active non-admin users other than the caller) — used for things like picking a colleague/assignee in the mobile app.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `User` model tenant-scoped; only users in the caller's tenant are listed.

**Permissions/role requirements:** None (any authenticated user), but the caller is authenticated implicitly via `Auth::id()` — returns 401 if somehow unauthenticated.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/fetch-all-user" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully",
  "data": [
    {
      "id": 12,
      "name": "Jane Doe",
      "employee_id": "EMP0012",
      "email": "jane.doe@example.com",
      "contact": "9876543210",
      "profile_image": "https://vpshrms.shurttech.com/uploads/users/....jpg",
      "designation": "Software Engineer",
      "department": "Engineering",
      "joining_date": "2024-05-01",
      "reporting_head": "John Manager"
    }
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 401 | `Auth::id()` returns null | `{"success": false, "message": "User not authenticated"}` |
| 200 | No matching users found | `{"success": false, "message": "No users found"}` |
| 500 | Any exception | `{"success": false, "message": "Something went wrong", "error": "<exception message>"}` |

**Pagination:** None — returns the full filtered list.

**Filters:** Hardcoded server-side: `role != 'admin'`, `id != <caller id>`, `status = 1`. No client-supplied filters.

**Special behavior / notes:** `profile_image` falls back to `<app.url>/profile2.jpg` when the user has no uploaded photo. `reporting_head` here is only the primary reporting head's name (single string), unlike `user/profile`'s richer `reporting_heads` array.

---

## GET /user/profile

**Purpose:** Fetch the authenticated employee's own full profile (personal info, basic details, bank details, job details, location, documents).

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** Implicit — the profile returned is always the JWT-authenticated user's own record (`Auth::id()`), which itself belongs to the current tenant.

**Permissions/role requirements:** None (self-service; any authenticated user can view their own profile).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None (read-only endpoint).

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/user/profile" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully",
  "data": {
    "personal_information": {
      "name": "Jane Doe",
      "employee_id": "EMP0012",
      "email": "jane.doe@example.com",
      "contact": "9876543210",
      "role": "employee"
    },
    "basic_details": {
      "father_name": null,
      "mother_name": null,
      "dob": "1995-03-10",
      "gender": "female",
      "profile_image": "https://vpshrms.shurttech.com/uploads/users/....jpg",
      "blood_group": "O+",
      "marital_status": "single",
      "nationality": "Indian",
      "alternate_phone": null,
      "personal_email": "jane@personal.com",
      "aadhaar_no": null,
      "pan_no": null,
      "languages": ["English", "Hindi"]
    },
    "bank_details": {
      "account_number": "XXXXXXXXXXXX",
      "ifsc": "HDFC0001234",
      "bank_name": "HDFC Bank",
      "branch_name": "MG Road"
    },
    "job_details": {
      "designation": "Software Engineer",
      "department": "Engineering",
      "joining_date": "2024-05-01",
      "employment_type": "full_time",
      "reporting_head": "John Manager",
      "reporting_heads": [
        {"id": 3, "name": "John Manager", "is_primary": true}
      ]
    },
    "location": {
      "country": "India",
      "state": "Karnataka",
      "city": "Bengaluru",
      "country_code": "IN",
      "state_code": "KA",
      "city_code": "BLR",
      "address": "123 Main St",
      "permanent_address": "456 Home St",
      "pincode": "560001"
    },
    "documents": {
      "experience_letter": null,
      "tenth_marksheet": "https://vpshrms.shurttech.com/uploads/....pdf",
      "twelfth_marksheet": null,
      "highest_qualification_certificate": null
    },
    "documents_list": [
      {
        "id": 5,
        "document_type": "other",
        "document_type_label": "Other",
        "document_name": "Offer Letter",
        "file_url": "https://vpshrms.shurttech.com/....pdf"
      }
    ]
  }
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 401 | `Auth::id()` returns null | `{"success": false, "message": "User not authenticated"}` |
| 404 | User record not found (edge case) | `{"success": false, "message": "User information not found"}` |
| 500 | Any exception | `{"success": false, "message": "Something went wrong", "error": "<exception message>"}` |

**Pagination:** None.

**Filters:** None — always the caller's own data.

**Special behavior / notes:** `basicDetails.language` is stored as a JSON array of language IDs and resolved to names via the `Language` model. `reporting_head` (singular) is kept for older app builds and reflects only the primary reporting head; `reporting_heads` is the full multi-reporting-head set with an `is_primary` flag per entry (this tenant/app supports multiple reporting heads per employee). `documents_list` is the full dynamic per-employee document set (any count, any `document_type` including "other"), separate from the four fixed `documents` fields (experience letter, 10th/12th marksheet, highest qualification certificate).

---

## POST /user/profile-update

**Purpose:** Update the authenticated employee's own basic details, profile photo, and address/location.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** Always updates the JWT-authenticated user's own `UserBasicDetail`/`UserLocation` rows.

**Permissions/role requirements:** None (self-service).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |
| Content-Type | Yes | `multipart/form-data` (required for the optional file upload) |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| profile_photo | file (image) | No | `nullable\|image\|mimes:jpg,jpeg,png\|max:2048` (KB, i.e. ≤2MB) |
| alternate_phone | string | No | `nullable\|digits:10` |
| personal_email | string | No | `nullable\|email` |
| gender | string | No | `nullable\|in:male,female,other` |
| dob | date | No | `nullable\|date` |
| country | string | No | `nullable\|exists:countries,country_code` |
| state | string | No | `nullable\|exists:states,state_code` |
| city | string | No | `nullable\|exists:cities,city_code` |
| permanent_address | string | No | `nullable\|string` |
| current_address | string | No | `nullable\|string` |
| pin_code | string | No | `nullable\|digits:6` |

All fields are optional; any field omitted keeps its existing stored value (`$request->field ?? $existing`).

**File uploads:** `profile_photo` — image only (`jpg`, `jpeg`, `png`), max 2048 KB. Stored on the **public** disk under `public/uploads/users/`, filename pattern `time()_<employee_id>_profile.<ext>`. The previous photo file (if any) is deleted from disk before the new one is written.

**Sample request:**

```bash
curl -X POST "https://vpshrms.shurttech.com/api/user/profile-update" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -F "alternate_phone=9123456780" \
  -F "personal_email=jane.new@personal.com" \
  -F "current_address=789 New St" \
  -F "country=IN" -F "state=KA" -F "city=BLR" -F "pin_code=560002" \
  -F "profile_photo=@/path/to/photo.jpg"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Profile updated successfully"
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Validation failure (any rule above) | `{"success": false, "message": "<first validation error>"}` |
| 500 | Exception during DB transaction (rolled back) | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Pagination:** None.

**Filters:** None.

**Special behavior / notes:** Validation failures return HTTP `200`, not `422` — the mobile app must inspect `success`/`message`, not the status code, to detect a validation error. The whole update runs inside a `DB::beginTransaction()`/`commit()`/`rollBack()` block. If the user has no existing `UserBasicDetail`/`UserLocation` row yet, one is created rather than updated. `country`/`state`/`city` are validated against `countries.country_code`/`states.state_code`/`cities.city_code` — use the codes returned by `GET /get-country`, `/get-states`, `/get-cities`, not display names.

---

## GET /get-country

**Purpose:** Lookup list of active countries, for populating the address form (cascades into `/get-states`).

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** None — `Country` is a global reference table, not tenant-scoped.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/get-country" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Country retrieved successfully",
  "data": [
    {"country_code": "IN", "name": "India", "short_name": "IN"}
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception | `{"success": false, "message": "Failed to retrieve countries. Please try again."}` |

**Pagination:** None.

**Filters:** None (hardcoded to `status = 1`, ordered by `name` ascending).

**Special behavior / notes:** First step of the country → state → city cascade used by `/user/profile-update`; the returned `country_code` is what you pass as `country_code` to `/get-states`.

---

## GET /get-states

**Purpose:** Lookup list of active states for a given country — second step of the address cascade.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** None — `State` is a global reference table.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| country_code | string | Yes | `required\|exists:countries,country_code` (query param) |

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/get-states?country_code=IN" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "States retrieved successfully",
  "data": [
    {"state_code": "KA", "name": "Karnataka", "short_name": "KA"}
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | `country_code` missing or invalid | `{"success": false, "message": "<validation error>"}` |
| 500 | Any exception | `{"success": false, "message": "Failed to retrieve states. Please try again."}` |

**Pagination:** None.

**Filters:** `country_code` (required) — states are additionally filtered to `status = 1`, ordered by `name` ascending.

**Special behavior / notes:** Validation failure returns HTTP `200`, not `422` — same convention as `/user/profile-update`.

---

## GET /get-cities

**Purpose:** Lookup list of active cities for a given state — final step of the address cascade.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** None — `City` is a global reference table.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| state_code | string | Yes | `required\|exists:states,state_code` (query param) |

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/get-cities?state_code=KA" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Cities retrieved successfully",
  "data": [
    {"city_code": "BLR", "name": "Bengaluru", "short_name": "BLR"}
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | `state_code` missing or invalid | `{"success": false, "message": "<validation error>"}` |
| 500 | Any exception | `{"success": false, "message": "Failed to retrieve cities. Please try again."}` |

**Pagination:** None.

**Filters:** `state_code` (required) — cities are additionally filtered to `status = 1`, ordered by `name` ascending.

**Special behavior / notes:** Validation failure returns HTTP `200`, not `422`.

---

## GET /user/location/tracks

**Purpose:** Fetch a user's GPS field-tracking trail, task load, and attendance for a specific date — used by both the employee (own data) and, when `user_id` is supplied, a manager viewing a team member's trail.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `User`/`Attendance`/`AttendanceTrackingPoint` are tenant-scoped via `TenantTrait`; when `user_id` is supplied it must resolve to a user found by `User::find()` (tenant scope applies to the query, so a cross-tenant id simply returns "not found").

**Permissions/role requirements:** None enforced in code beyond authentication — there is **no** ownership/scope check on the `user_id` parameter (any authenticated user can pass any `user_id` in their own tenant and view that user's tracks/attendance/tasks).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| date | date | Yes | `required\|date` — the date to fetch tracks/attendance/tasks for |
| user_id | integer | No | `nullable\|exists:users,id` — defaults to the authenticated user when omitted |
| include_heatmap | boolean | No | `nullable\|boolean` — accepted but not read by the current implementation |
| include_path | boolean | No | `nullable\|boolean` — accepted but not read by the current implementation |
| interval_minutes | integer | No | `nullable\|integer\|min:5\|max:60`, default `15` — accepted and passed to statistics, but not currently used to bucket/thin the returned track points |

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/user/location/tracks?date=2026-09-27&user_id=12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK` (has attendance and tracks)

```json
{
  "success": true,
  "message": "Location tracks fetched successfully",
  "data": {
    "date": "2026-09-27",
    "attendance": {
      "clock_in": "2026-09-27 09:05:00",
      "clock_out": "2026-09-27 18:10:00",
      "total_hours": "9.08"
    },
    "location_tracks": [
      {
        "id": 101,
        "track_time": "2026-09-27 09:10:00",
        "latitude": "12.9716",
        "longitude": "77.5946",
        "address": "MG Road, Bengaluru",
        "battery_per": 87
      }
    ],
    "statistics": {
      "total_tracks": 40,
      "first_track_time": "2026-09-27 09:10:00",
      "last_track_time": "2026-09-27 18:00:00",
      "tracking_duration_hours": 8.83,
      "tracking_duration_minutes": 530
    },
    "tasks": {
      "counts": {
        "total": 3, "critical": 0, "high": 1, "medium": 2, "low": 0,
        "pending": 1, "in_progress": 2, "due_today": 1, "overdue": 0
      },
      "list": [ { "id": 55, "task_code": "TSK-0055", "title": "Site visit", "priority": "high", "status": "in_progress", "deadline_date": "2026-09-27", "task_date": "2026-09-25", "project_name": "Client X Rollout", "project_code": "PRJ-01", "deadline_status": "Due Today", "days_remaining": 0 } ]
    }
  }
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<validation error>"}` |
| 404 | `user_id` supplied but not found | `{"success": false, "message": "User not found"}` |
| 401 | No authenticated user resolvable | `{"success": false, "message": "User not authenticated"}` |
| 422 | `ValidationException` thrown internally (belt-and-suspenders catch; in practice the manual `Validator` above returns 200 first) | `{"success": false, "message": "Validation failed", "errors": {...}}` |
| 500 | Any other exception | `{"success": false, "message": "Failed to fetch location tracks: <exception message>"}` |

**Pagination:** None.

**Filters:** `date` (required), `user_id` (optional target user).

**Special behavior / notes:** Three distinct "empty" shapes exist depending on data availability, all still HTTP 200 `success: true`:
1. No `Attendance` row for that date at all → `message: "No attendance record found for this date"`, all attendance/statistics fields `null`, `location_tracks: []`, tasks still populated.
2. Attendance exists but no `AttendanceTrackingPoint` rows → `message: "No location tracks found for this date"`, `data.has_attendance: true`, `data.has_tracks: false`, `statistics: {total_tracks: 0, tracking_duration: 0}` (note: different/smaller statistics shape than the full-data response).
3. Both exist → full response shown above.

The `tasks` block is independent of attendance/tracks and is computed via a raw SQL query joining `tasks`/`task_assigns`/`projects` for tasks assigned to the target user whose `task_date <= date <= deadline_date`.

---

## GET /user/team-view

**Purpose:** Manager/HR/Admin dashboard: today's attendance/leave/week-off status for the caller's team (or the whole company).

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `User`/`Attendance`/`Leave`/`Holiday` all tenant-scoped via `TenantTrait`.

**Permissions/role requirements:** No route-level `permission:` middleware, but the controller itself calls `RbacService::scopeFor($authUser, 'team', 'view')` and requires a scope of `'team'` or `'company'` — a user whose `team,view` scope is `null` or `'own'` is rejected in-controller (see error table).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/user/team-view" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "status": true,
  "message": "Team status fetched successfully",
  "data": {
    "teams": [
      {
        "id": 12,
        "employee_id": "EMP0012",
        "name": "Jane Doe",
        "email": "jane.doe@example.com",
        "role": "employee",
        "designation": "Software Engineer",
        "department": "Engineering",
        "profile_image": "https://vpshrms.shurttech.com/uploads/users/....jpg",
        "punch_in": "2026-09-27 09:05:00",
        "punch_out": null,
        "clock_in_lat": "12.9716",
        "clock_in_long": "77.5946",
        "clock_in_address": "MG Road, Bengaluru",
        "clock_out_lat": null,
        "clock_out_long": null,
        "clock_out_address": null,
        "status": "Present (Not Checked Out)",
        "is_week_off": false
      }
    ],
    "count": {
      "present": 5, "absent": 1, "on_leave": 2, "holiday": 0, "weekoff": 0, "total": 8
    },
    "date": "2026-09-27",
    "day": "Sunday"
  }
}
```

Note: this endpoint's top-level success key is **`status`**, not `success` — different from most other endpoints in this module.

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Caller's `team,view` RBAC scope is `null` or `'own'` (not a manager/HR/admin) | `{"success": false, "message": "Unauthorized access. Only managers and Hr can view this page."}` (note: this specific error uses `success`, not `status`, unlike the success response) |
| 500 | Any exception (also logged via `Log::error`) | `{"status": false, "message": "An error occurred. Please try again later."}` |

**Pagination:** None — returns the full team roster in one array.

**Filters:** None (query-parameterized); scope is entirely determined by the caller's RBAC grant.

**Special behavior / notes:** Team membership depends on the resolved scope: `'company'` scope (admin/HR) returns every active, non-admin user in the tenant; `'team'` scope (manager) returns only users reporting to the caller via **any** of their reporting heads (`User::managedBy()`, supporting the multi-reporting-head model). Per-member `status` is derived with this precedence: Holiday > On Leave (approved leave covering today) > Present/Present (Not Checked Out) (from today's `Attendance` row) > Week Off (from `user_weekoffs`, supporting both `date_based` and `day_based` recurring week-offs) > Absent (default).

---

## GET /user/team-profile/{id}

**Purpose:** Detailed drill-down into one team member's profile plus a rolling ~2-month attendance/leave/holiday calendar and (if that member is themselves a manager) their own sub-team's today-status.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `User::find($id)` — tenant scope applies via `TenantTrait`, so an `{id}` belonging to another tenant resolves to "not found".

**Permissions/role requirements:** Enforced in-controller via `AuthorizesByScope::scopeCoversOwner($authUser, 'team', 'view', (int) $id)` — the caller must have a `team,view` scope of `'company'` (any employee), `'team'` where `$id` is a direct/indirect reportee (or themselves), or `'own'` only if `$id === $authUser->id`. Anyone else is rejected (see error table). No route-level `permission:` middleware.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| id | integer | Yes | Path parameter — target user's id |

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/user/team-profile/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "User profile and attendance data fetched successfully",
  "data": {
    "personal_information": { "id": 12, "name": "Jane Doe", "employee_id": "EMP0012", "email": "jane.doe@example.com", "contact": "9876543210", "role": "employee" },
    "teams": [],
    "basic_details": { "...": "same shape as GET /user/profile" },
    "bank_details": { "...": "same shape as GET /user/profile" },
    "job_details": { "...": "same shape as GET /user/profile" },
    "location": { "...": "same shape as GET /user/profile" },
    "attendance_summary": {
      "month": "September 2026",
      "month_start": "2026-09-01",
      "month_end": "2026-09-28",
      "counts": {
        "total_days": 58, "present": 20, "absent": 2, "on_leave": 3, "holiday": 1,
        "weekend": 8, "checked_in_only": 1, "work_days": 27, "upcoming": 30
      },
      "today": { "...": "one attendance_details row for today, plus location_tracks/total_tracks" }
    },
    "attendance_details": {
      "period": { "start_date": "2026-09-01", "end_date": "2026-09-28" },
      "attendances": [
        {
          "user_id": 12, "name": "Jane Doe", "email": "jane.doe@example.com",
          "date": "2026-09-27", "day_name": "Sunday",
          "clock_in": "2026-09-27 09:05:00", "clock_out": null, "total_hours": null,
          "leave_type": null, "leave_reason": null, "leave_session": null,
          "holiday_name": null, "message": null,
          "day_status": "Checked In Only", "task_count": 2
        }
      ]
    },
    "documents": { "...": "same shape as GET /user/profile" },
    "documents_list": [ "..." ]
  }
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Caller's scope does not cover `{id}` | `{"success": false, "message": "Unauthorized access. Only managers and Hr can view this page."}` |
| 200 | `{id}` not found (tenant-scoped) | `{"success": false, "message": "User not found"}` |
| 500 | Any exception (also logged via `Log::error`) | `{"success": false, "message": "Something went wrong: <exception message>"}` |

**Pagination:** None.

**Filters:** None — driven entirely by the `{id}` path parameter.

**Special behavior / notes:**
- **Date window**: the attendance calendar covers roughly `date('Y-m-01', strtotime('-30 days'))` through `date('Y-m-28', strtotime('+30 days'))` — in practice close to "last ~30 days through next ~30 days," not a clean calendar month; it always includes future/"Upcoming" dates.
- **`teams` sub-array**: populated only if the target user's `role` is `manager`, `admin`, or `hr` — lists that user's own direct reportees (via `user_reporting_heads`) with today's status, same shape as `team-view`'s `teams` array. Empty array otherwise.
- **`day_status` values**: `Holiday`, `First Half Leave` / `Second Half Leave` / `Full Day Leave`, `Present`, `Checked In Only`, `Weekend` (derived from `user_weekoffs`, not hardcoded Sat/Sun), `Absent`, or — for future dates with no scheduled event — `Upcoming`.
- **Week-off detection**: reads the `user_weekoffs` table, supporting both `date_based` (explicit date range) and `day_based` (recurring day-of-week) entries.
- **`today`**: the `attendance_details.attendances` entry matching today's date is duplicated into `attendance_summary.today`, additionally enriched with `location_tracks`/`total_tracks` (from `AttendanceTrackingPoint`) — but only when today's `day_status` is `Present` or `Checked In Only`.
- **`task_count`**: per-day count of tasks assigned to the target user active on that date, from the same raw-SQL `tasks`/`task_assigns` join used by `/user/location/tracks`.

---

## GET /leave-type

**Purpose:** List all active leave types with the caller's current available balance in each.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `LeaveType` and `LeaveBalance` are tenant-scoped via `TenantTrait`.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/leave-type" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": [
    {
      "id": 1,
      "name": "Casual Leave",
      "credit_type": "monthly",
      "credit_value": "1.00",
      "available_balance": "6.00"
    },
    {
      "id": 2,
      "name": "Loss of Pay",
      "credit_type": "none",
      "credit_value": "0.00",
      "available_balance": "0"
    }
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception | `{"success": false, "message": "An error occured. Please try again later."}` |

**Pagination:** None.

**Filters:** None (hardcoded to `status = '1'`).

**Special behavior / notes:** `available_balance` is `"0"` (string) for any leave type with no `LeaveBalance` row for the caller yet, e.g. a Loss-of-Pay (LWP) type that is never credited. Balances come from the `leave_balances` table, keyed per `(user_id, leave_type_id)`.

---

## GET /view-leave

**Purpose:** List the authenticated employee's own leave applications plus their aggregate and per-type leave balances.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Leave`/`LeaveBalance`/`LeaveTransaction` tenant-scoped via `TenantTrait`; always the caller's own `user_id`.

**Permissions/role requirements:** None (self-service).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/view-leave" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": {
    "leaves": [
      {
        "id": 101,
        "leave_id": "LV-000101",
        "leave_type": "Casual Leave",
        "reason": "Family function",
        "date": "2026-09-15",
        "session": "fullday",
        "status": "pending",
        "end_date": "2026-09-16",
        "end_session": "fullday",
        "total_days": 2,
        "file_url": null
      }
    ],
    "leave_balance": "12.5",
    "type_balance": [
      { "id": 1, "type": "Casual Leave", "balance": 6.0 },
      { "id": 2, "type": "Sick Leave", "balance": 6.5 }
    ]
  }
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception | `{"success": false, "message": "An error occured. Please try again later.<exception message>"}` |

**Pagination:** None — returns the caller's full leave history in one array.

**Filters:** None (always the caller's own leaves).

**Special behavior / notes:** As of the current schema, one `Leave` row represents an entire leave **application** (a date range), not one row per day — `date`/`session` are the legacy fields (`start_date`/`start_session` aliased), while `end_date`/`end_session`/`total_days` describe the full requested range and its computed working-day count (weekends/holidays excluded). `file_url` is a full URL built from the stored `file` path (the supporting document uploaded with the application), or `null` if none was attached. `leave_balance` is the sum of all `type_balance` entries, formatted as a string.

---

## POST /apply-leave

**Purpose:** Submit a new leave application for the authenticated employee.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** Created `Leave` row is implicitly scoped to the caller's tenant via `TenantTrait` on creation; balance checks use the caller's own `LeaveBalance` rows.

**Permissions/role requirements:** None (self-service — any authenticated employee can apply for their own leave).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |
| Content-Type | Yes | `multipart/form-data` (supports optional file attachment) |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| leave_type | integer | Yes | `required\|exists:leave_types,id` |
| start_date | date | Yes | `required\|date` |
| start_session | string | Yes | `required\|in:session1,session2,fullday` |
| end_date | date | Yes | `required\|date` |
| end_session | string | Yes | `required\|in:session1,session2,fullday` |
| file | file | No | `nullable\|file\|max:2048` (KB, ≤2MB) — supporting document |
| reason | string | Yes | `required\|max:500` |

**File uploads:** `file` — any file type, max 2048 KB. Stored on the **public** disk under `public/uploads/leave/document/`, filename pattern `time()_<uniqid>.<ext>`.

**Sample request:**

```bash
curl -X POST "https://vpshrms.shurttech.com/api/apply-leave" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -F "leave_type=1" \
  -F "start_date=2026-10-05" \
  -F "start_session=fullday" \
  -F "end_date=2026-10-06" \
  -F "end_session=fullday" \
  -F "reason=Personal work" \
  -F "file=@/path/to/document.pdf"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Leave request submitted successfully"
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Validation failure (any rule above) | `{"success": false, "message": "<first validation error>"}` |
| 200 | `end_date` before `start_date` | `{"success": false, "message": "End date cannot be before start date."}` |
| 200 | Selected date range has zero working days (all weekend/holiday) | `{"success": false, "message": "The selected date range has no working days to apply leave for."}` |
| 200 | Leave type switched off for this employee, or their custom notice / maximum-length rule is not met (Employee 360 → Policies, 2026-10-02) | `{"success": false, "message": "{Leave type} is not available for this employee."}` / `"… requires at least N day(s) notice."` / `"… cannot be taken for more than N consecutive day(s)."` |
| 200 | Insufficient balance (non-LWP leave type only) | `{"success": false, "message": "Insufficient leave balance. You have {available} days available but requested {requested} days."}` |
| 500 | Any exception (transaction rolled back) | `{"success": false, "message": "<exception message>"}` |

**Pagination:** None.

**Filters:** None.

**Special behavior / notes:**
- Every validation and business-rule failure returns HTTP `200`, not `422`/`400` — the app must check `success`.
- `total_days`/`leave_count` are computed by `LeaveService::computeLeaveDays()`, which excludes weekends and tenant holidays from the requested `start_date`–`end_date` range, adjusting for half-day sessions at the start/end.
- Balance is checked against `leave_balances` only when the selected leave type is **not** the tenant's configured Loss-of-Pay (LWP) type (`LeaveService::isLwpId()`) — LWP leave can always be applied regardless of balance.
- On success, the row is created with `status = 'pending'`, and `LeaveNotificationService::notifyLeaveSubmitted()` fires notifications to the reporting head(s)/HR/admin (a notification failure is logged but does not fail the request).
- The full requested `start_date`/`end_date` range is stored as-is (even though `total_days` reflects only working days within it).

---

## GET /view-team-leave

**Purpose:** Manager/HR/Admin view of leave applications across their team or the whole company.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Leave`/`User`/`UserJobDetail`/`LeaveType` tenant-scoped via `TenantTrait`.

**Permissions/role requirements:** Route-level `permission:leave,view` middleware **plus** an in-controller RBAC scope check (`RbacService::scopeFor($authUser, 'leave', 'view')`) — a caller whose resolved scope is `null` or `'own'` is rejected even if they somehow passed the route middleware (e.g. a custom role with `leave,view` granted but scoped to `'own'`).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/view-team-leave" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Leave data fetched successfully!",
  "data": [
    {
      "id": 101,
      "leave_id": "LV-000101",
      "employee_name": "Jane Doe",
      "employee_role": "employee",
      "leave_type": "Casual Leave",
      "start_date": "2026-09-15",
      "end_date": "2026-09-16",
      "start_session": "fullday",
      "end_session": "fullday",
      "total_days": 2,
      "reason": "Family function",
      "status": "pending",
      "created_at": "2026-09-10T08:30:00.000000Z",
      "file_url": null
    }
  ],
  "total_count": 1
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 403 | Route middleware `permission:leave,view` fails | `{"message": "You do not have permission for this action."}` |
| 403 | In-controller scope check fails (`scope` is `null` or `'own'`) | `{"success": false, "message": "Unauthorized access. Only managers, HR, and admins can view this data."}` |
| 500 | Any exception | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Pagination:** None — returns the full filtered list plus `total_count`.

**Filters:** None client-supplied; scope-driven server-side: `'team'` scope restricts to users reporting to the caller (via `user_reporting_heads`, any reporting head); `'company'` scope returns every leave in the tenant. Results are ordered by `leaves.created_at` descending.

**Special behavior / notes:** This is a different (richer, list-all) view from `GET /view-leave` — it includes `employee_name`/`employee_role` and is not restricted to the caller's own leaves.

---

## GET /update-team-leave-status/{id}

**Purpose:** Approve or reject (cancel) a pending leave application belonging to a team member.

**Auth:** JWT bearer + singleLogin. Note this is a `GET` request that performs a write (per the route definition), not a `POST`/`PATCH`.

**Tenant scope:** `Leave`/`UserJobDetail` tenant-scoped via `TenantTrait`; `{id}` must resolve within the caller's tenant.

**Permissions/role requirements:** Route-level `permission:leave,approve` middleware **plus** an in-controller `scopeCoversOwner($authUser, 'leave', 'approve', (int) $leave->user_id)` check — the caller's `leave,approve` scope must cover the specific leave's owner (`'company'` = anyone, `'team'` = the caller or their reportee, `'own'` = only their own leave).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| id | integer | Yes | Path parameter — the `leaves.id` to decide |
| status | string | Yes | `required\|in:approved,cancelled` (query/body param — this is a GET route, so typically sent as a query string) |
| remarks | string | No | `nullable\|string\|max:500` |

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/update-team-leave-status/101?status=approved&remarks=Approved%2C%20enjoy%20your%20leave" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK` (direct-approval path, no tenant workflow configured)

```json
{
  "success": true,
  "message": "Leave approved successfully"
}
```

Or, when the tenant has a multi-level `ApprovalService` workflow configured for `leave`:

```json
{
  "success": true,
  "message": "Recorded. Awaiting the next approval level.",
  "workflow_status": "pending"
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Validation failure (`status` not `approved`/`cancelled`, or `remarks` too long) | `{"success": false, "message": "<validation error>"}` |
| 403 | Route middleware `permission:leave,approve` fails | `{"message": "You do not have permission for this action."}` |
| 404 | `{id}` not found (tenant-scoped join with `user_job_details`) | `{"success": false, "message": "Leave not found."}` |
| 403 | `scopeCoversOwner()` returns false (leave belongs outside caller's scope) | `{"success": false, "message": "You are not authorized to update this leave."}` |
| 400 | Leave is not currently `pending` | `{"success": false, "message": "This leave has already been processed."}` |
| 400 | Workflow path: insufficient balance detected mid-approval | `{"success": false, "message": "<InsufficientLeaveBalanceException message>"}` |
| 403 | Workflow path: `RuntimeException` from the approval engine | `{"success": false, "message": "<exception message>"}` |
| 400 | Direct path: `LeaveService::approvePendingLeave()`/`cancelPendingLeave()` reports failure (e.g. insufficient balance at approval time) | the raw `$result` array, e.g. `{"success": false, "message": "..."}` |
| 500 | Any other exception (also logged) | `{"success": false, "message": "Something went wrong"}` |

**Pagination:** None.

**Filters:** None (acts on the single `{id}`).

**Special behavior / notes:**
- Only a leave currently in `status = 'pending'` can be decided; there is a separate revoke flow (not in this module's route list) for cancelling an already-approved leave.
- `status=cancelled` is this module's vocabulary for a manager **rejection** (not the employee's own withdrawal) — `Leave` status values are `pending`/`approved`/`cancelled` (lower-case), distinct from the `Request` (WFH/Travel) module's upper-case vocabulary.
- If the tenant has a configured `ApprovalService` workflow for the `'leave'` handler (Settings → Approvals), the decision routes through the generic multi-level approval engine first (`cancelled` is translated to `'rejected'` for the engine's vocabulary); the engine's own handler sends the approved/rejected notification in that case. If no workflow is configured, execution falls through to the direct `LeaveService::approvePendingLeave()`/`cancelPendingLeave()` path, which also sends notifications via `LeaveNotificationService` (failure to notify is logged, not fatal) and updates `leave_balances` accordingly (approving debits the balance; cancelling a pending request has no balance effect since nothing was debited yet).

---

## GET /view-announcement

**Purpose:** List announcements created by/attributed to the authenticated user, dated today or later and not yet expired, along with the caller's acknowledgment state for each.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Announcement`/`AnnouncementAcknowledgment` tenant-scoped via `TenantTrait`.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/view-announcement" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": [
    {
      "id": 8,
      "title": "Office closed Oct 2",
      "description": "Gandhi Jayanti holiday",
      "acknowledge": 1,
      "expire_date": "2026-10-10",
      "acknowledged_at": null,
      "is_acknowledged": false,
      "file_url": null,
      "image_url": "https://vpshrms.shurttech.com/uploads/announcement/image/....jpg"
    }
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception | `{"success": false, "message": "An error occured. Please try again later."}` |

**Pagination:** None.

**Filters:** None client-supplied. Server-side: `announcements.user_id = <caller id>` (i.e. announcements the caller themselves authored/is attributed to — see note below), `created_at >= now()` (date portion), and `notExpired()` (a model scope excluding announcements whose `expire_date` has passed).

**Special behavior / notes:** Despite the route name, this endpoint filters to `announcements.user_id = Auth::id()` — i.e. it returns announcements attributed to the caller (e.g. ones they created via `POST /create-announcement`), **not** the general company-wide feed. For the general "all announcements visible to me" feed, see `GET /all-announcement` below. `is_acknowledged` is computed via a `LEFT JOIN` against `announcement_acknowledgments` scoped to the caller's own acknowledgment row.

---

## POST /create-announcement

**Purpose:** Create a new announcement (with optional image/file attachment), visible tenant-wide.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** Created `Announcement` row is scoped to the caller's tenant via `TenantTrait`; `user_id` is set to the caller (`auth()->id()`).

**Permissions/role requirements:** None enforced at the route or controller level for this endpoint (no `permission:announcements,create` middleware present on this particular mobile route, unlike the web panel's equivalent) — any authenticated user can create an announcement via this endpoint.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |
| Content-Type | Yes | `multipart/form-data` (for optional image/file) |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| title | string | Yes | `required\|string\|max:255` |
| image | file (image) | No | `nullable\|image\|max:2048` (KB, ≤2MB) |
| file | file | No | `nullable\|file\|max:5120` (KB, ≤5MB) |
| acknowledge | string/int | No | `nullable\|in:0,1` — whether recipients must acknowledge this announcement |
| description | string | No | `nullable\|string` |
| expire_date | date | No | `nullable\|date` |

**File uploads:**
- `image` — image only, max 2048 KB. Stored on the **public** disk under `public/uploads/announcement/image/`, filename `time()_<uniqid>.<ext>`.
- `file` — any file type, max 5120 KB. Stored on the **public** disk under `public/uploads/announcement/file/`, filename `time()_<uniqid>.<ext>`.

**Sample request:**

```bash
curl -X POST "https://vpshrms.shurttech.com/api/create-announcement" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -F "title=Office closed Oct 2" \
  -F "description=Gandhi Jayanti holiday" \
  -F "acknowledge=1" \
  -F "expire_date=2026-10-10" \
  -F "image=@/path/to/banner.jpg"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Announcement created successfully"
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | Validation failure (any rule above) | `{"success": false, "message": "<first validation error>"}` |
| 500 | Any exception | `{"success": false, "message": "<exception message>"}` |

**Pagination:** None.

**Filters:** None.

**Special behavior / notes:** `status` defaults to `1` (active) if not supplied in the request (not part of the documented validated field set, but read via `$request->status ?? 1`). `acknowledge` defaults to `0` (no acknowledgment required) when omitted. On success, `AnnouncementNotificationService::notifyAnnouncementCreated()` fires push/DB notifications to relevant recipients.

---

## GET /all-announcement

**Purpose:** The general company-wide announcement feed — all non-expired announcements visible to the caller, with acknowledgment state.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Announcement`/`AnnouncementAcknowledgment` tenant-scoped via `TenantTrait`.

**Permissions/role requirements:** None.

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None.

**File uploads:** None.

**Sample request:**

```bash
curl -X GET "https://vpshrms.shurttech.com/api/all-announcement" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK`

```json
{
  "success": true,
  "message": "Data fetched successfully!!!",
  "data": [
    {
      "id": 8,
      "title": "Office closed Oct 2",
      "description": "Gandhi Jayanti holiday",
      "acknowledge": 1,
      "expire_date": "2026-10-10",
      "acknowledged_at": "2026-09-20T10:15:00.000000Z",
      "is_acknowledged": true,
      "file_url": null,
      "image_url": "https://vpshrms.shurttech.com/uploads/announcement/image/....jpg"
    }
  ]
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 500 | Any exception | `{"success": false, "message": "An error occured. Please try again later.<exception message>"}` |

**Pagination:** None — returns the full non-expired list, newest first (`orderBy('announcements.id', 'DESC')`).

**Filters:** None client-supplied. Server-side: `notExpired()` scope only — unlike `GET /view-announcement`, this is **not** filtered to `user_id = caller`, i.e. it is the tenant-wide feed.

**Special behavior / notes:** This is the endpoint the mobile app should use for a general "Announcements" list/feed screen; `GET /view-announcement` is narrower (caller-authored only) despite its similar-sounding name. `is_acknowledged`/`acknowledged_at` reflect the caller's own acknowledgment record, if any, via the same `LEFT JOIN` pattern as `/view-announcement`.

---

## POST /acknowledge-announcement/{id}

**Purpose:** Record the authenticated employee's acknowledgment of a specific announcement that requires it.

**Auth:** JWT bearer + singleLogin.

**Tenant scope:** `Announcement::find($id)` — no explicit tenant filter is applied in this lookup (`Announcement::find()` rather than a tenant-scoped query builder call), though the `Announcement` model itself uses `TenantTrait`, so the global scope still constrains the lookup to the caller's tenant under normal Eloquent resolution.

**Permissions/role requirements:** None (self-service — any authenticated user can acknowledge an announcement visible to them).

**Headers:**

| Header | Required | Notes |
|---|---|---|
| Authorization | Yes | `Bearer <jwt>` |
| Device-Token | Yes | Must match `users.last_login_token` |

**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| id | integer | Yes | Path parameter — the `announcements.id` to acknowledge |

**File uploads:** None.

**Sample request:**

```bash
curl -X POST "https://vpshrms.shurttech.com/api/acknowledge-announcement/8" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>"
```

**Success response:** `200 OK` (announcement requires acknowledgment)

```json
{
  "success": true,
  "message": "Announcement acknowledged successfully",
  "data": {
    "announcement_id": 8,
    "is_acknowledged": true,
    "acknowledged_at": "2026-09-28T09:00:00.000000Z"
  }
}
```

Or, if the announcement does not require acknowledgment (`acknowledge = 0`):

```json
{
  "success": true,
  "message": "This announcement does not require acknowledgment",
  "data": {
    "announcement_id": 8,
    "is_acknowledged": false
  }
}
```

**Error responses:**

| HTTP Status | Condition | Example body |
|---|---|---|
| 200 | `{id}` not found | `{"success": false, "message": "Announcement not found"}` |
| 500 | Any exception | `{"success": false, "message": "Failed to acknowledge announcement"}` |

**Pagination:** None.

**Filters:** None (acts on the single `{id}`).

**Special behavior / notes:** Uses `AnnouncementAcknowledgment::firstOrCreate(['announcement_id' => ..., 'user_id' => Auth::id()], ['acknowledged_at' => now()])` — calling this endpoint again on an already-acknowledged announcement is idempotent: it returns the existing acknowledgment record's original `acknowledged_at` rather than overwriting it or erroring. A "not found" result also returns HTTP `200`, not `404`.
