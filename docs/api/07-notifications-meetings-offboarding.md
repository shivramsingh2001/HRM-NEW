# Notifications, Meetings (MoM) & Offboarding API

This module covers three self-contained legacy/mobile JSON API surfaces used by the Flutter app: **Notifications** (the generic in-app notification inbox backing `notifications` — read/unread state, delete), **Meetings / Minutes of Meeting (MoM)** (meeting scheduling, lifecycle, attendance RSVP, and the MoM-writer worklist), and **Offboarding** (employee self-service resignation submission, notice-period lookup, status timeline, and cancellation). All routes live in `routes/api.php` under the `tenant` middleware group, require a valid JWT bearer token plus a matching `Device-Token` header, and use **no fixed response envelope** — every action returns whatever shape its own controller code builds (usually `{success, message, data?}` but not guaranteed across all of them, e.g. `unread-count` uses top-level `unread_count`).

Base URL for every sample below: `https://vpshrms.shurttech.com`

---

## Common conventions (apply to every endpoint in this document)

**Auth:** JWT bearer token (`php-open-source-saver/jwt-auth`, `auth:api` guard) + `singleLogin` (`CheckSingleDeviceLogin`) middleware on every route.

- Missing/invalid/expired/blacklisted JWT → `401 {"message": "Unauthenticated."}`
- Missing or mismatched `Device-Token` (checked against `users.last_login_token`) → `401 {"status": false, "message": "Your account is logged in on another device. Please login again."}`

**Tenant scope:** There is no `tenant_id` request parameter anywhere in this module. The JWT-authenticated user's own `tenant_id` is the only source of tenant scope; tenant-scoped Eloquent models (`Meeting`, `MeetingParticipant`, `OffboardingRequest`, etc.) auto-filter via `TenantTrait`'s global scope. Laravel's built-in `notifications` table has no `tenant_id` column at all — notifications are scoped implicitly by `$user->notifications()` (the polymorphic `notifiable_id`/`notifiable_type` relation), never by tenant directly.

**Headers required on every route in this document:**

| Header | Required | Notes |
|---|---|---|
| `Authorization` | Yes | `Bearer <jwt>` |
| `Device-Token` | Yes | Must match the token stored on `users.last_login_token` at last login |
| `Accept` | Recommended | `application/json` |
| `Content-Type` | For POST routes | `application/json` (or `application/x-www-form-urlencoded`) |

These are omitted from the per-endpoint tables below to avoid repetition; only endpoint-specific headers are listed there.

---

## Notifications

Controller: `App\Http\Controllers\Api\Notification\NotificationController`. All routes use `GET` (including the "mark as read" / "delete" actions — this is a legacy convention, not a REST purity issue: nothing here is idempotent-unsafe in practice since read-state changes and deletes are user-invoked one-off actions). None of the Notification routes carry `permission:` middleware — any authenticated tenant user can manage their own notification inbox.

## GET /notifications/
**Purpose:** List the authenticated user's notifications, paginated, with optional read/unread and type filters.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit via `$user->notifications()` (the authenticated user's own polymorphic notifications relation) — no cross-tenant leakage possible since a JWT always belongs to exactly one user.
**Permissions/role requirements:** None (any authenticated user)
**Headers:** See Common conventions above.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | `read` or `unread`. Any other value (including omitted) returns both. |
| `type` | string | No | Matched against `JSON_EXTRACT(data, '$.type')` on the notification row. `all` (or omitted) disables the filter. |
| `per_page` | int | No | Default `15`. Passed straight to `paginate()`. |
| `page` | int | No | Standard Laravel paginator query param. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/?status=unread&per_page=10" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Accept: application/json"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Notifications fetched Successfully.",
  "data": [
    {
      "id": "9c1b2e3a-....",
      "type": "general",
      "title": "Meeting Scheduled",
      "message": "You have a new notification",
      "is_read": false,
      "read_at": null,
      "created_at_human": "5 minutes ago"
    }
  ],
  "pagination": {
    "unread_count": 3,
    "total_count": 42,
    "current_page": 1,
    "first_page_url": "https://vpshrms.shurttech.com/api/notifications/?page=1",
    "last_page": 5,
    "per_page": 10,
    "prev_page_url": null,
    "next_page_url": "https://vpshrms.shurttech.com/api/notifications/?page=2",
    "total": 42
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Auth::user() resolves null (should not normally happen once past middleware) | `{"success": false, "message": "Unauthenticated"}` |
| 401 | Missing/invalid JWT | `{"message": "Unauthenticated."}` |
| 401 | Device-Token mismatch | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |
| 500 | Any exception during query/format | `{"success": false, "message": "Failed to fetch notifications"}` |

**Pagination:** Standard Laravel paginator, wrapped in a custom `pagination` object (see success response above) rather than Laravel's default `links`/`meta` shape. `unread_count` and `total_count` are extra fields injected alongside the normal paginator fields.
**Filters:** `status` (read/unread), `type` (matches `data->type` JSON key).
**Special behavior / notes:** `id` is the notification's UUID primary key (Laravel's `DatabaseNotification`), not an incrementing int — required for `markAsRead`/`destroy`. `title`/`message` are pulled from the notification's `data` JSON blob with fallback chains (`title` ?? `message` ?? `'Notification'`; `message` ?? `body` ?? default string) since different Notification classes across the app populate slightly different keys.

---

## GET /notifications/unread-count
**Purpose:** Get the count of unread notifications for the authenticated user (e.g. for a bell-icon badge).
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit via `$user->unreadNotifications`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/unread-count" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Data fetched Successfully.",
  "unread_count": 3
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT | `{"message": "Unauthenticated."}` |
| 401 | Device-Token mismatch | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |

Note: this action has no try/catch — an unexpected internal error would surface as Laravel's default 500 HTML/JSON error page, not a custom `{success:false}` body.

**Pagination:** None
**Filters:** None
**Special behavior / notes:** `unread_count` is a top-level field, not nested under `data` — different shape from `index()`'s response. Do not assume a consistent envelope across this module's endpoints.

---

## GET /notifications/read/{id}
**Purpose:** Mark a single notification as read.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit — looked up via `$user->notifications()->findOrFail($id)`, so a notification belonging to another user's `notifiable_id` (any tenant) 404s.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` (path) | string (UUID) | Yes | The notification's `id`, as returned by `GET /notifications/`. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/read/9c1b2e3a-1111-2222-3333-444455556666" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Notifications marked as read"
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 404 | Notification `id` not found (or not owned by this user) | Laravel's default `findOrFail` 404 JSON (`{"message": "No query results for model [Illuminate\\Notifications\\DatabaseNotification] <id>"}`) — no custom catch in this action |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Idempotent in effect — calling it again on an already-read notification just re-sets `read_at` to a fresh timestamp (Laravel's `markAsRead()` always writes `read_at = now()` when it's currently null; calling on an already-read row is a no-op since the underlying implementation only updates when `read_at` is null). No response field returns the updated `read_at`.

---

## GET /notifications/read-all
**Purpose:** Mark every currently-unread notification for the authenticated user as read.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit via `$user->unreadNotifications`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/read-all" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "All notifications marked as read."
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |

No try/catch in this action — an unexpected error surfaces as Laravel's default 500 response.

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Bulk state transition — every row currently in `$user->unreadNotifications` (i.e. `read_at IS NULL`) gets `read_at` set in one pass.

---

## GET /notifications/destroy/{id}
**Purpose:** Permanently delete a single notification.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit — looked up via `$user->notifications()->findOrFail($id)`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` (path) | string (UUID) | Yes | The notification's `id`. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/destroy/9c1b2e3a-1111-2222-3333-444455556666" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Notification deleted."
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 404 | Notification `id` not found (or not owned by this user) | Laravel's default `findOrFail` 404 JSON |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Hard delete — this row is gone permanently (no soft-delete column on `notifications`). Works regardless of the notification's read/unread state.

---

## GET /notifications/destroy-all
**Purpose:** Permanently delete every notification the user has already read (bulk "clear read" action).
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Implicit via `$user->notifications()`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/notifications/destroy-all" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "All read notifications cleared."
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |

No try/catch in this action.

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Only deletes rows where `read_at IS NOT NULL` (`whereNotNull('read_at')->delete()`) — unread notifications are never touched by this endpoint. There is no "destroy all unread" or "destroy everything regardless of state" endpoint in this API.

---

## Meetings / Minutes of Meeting (MoM)

Controller: `App\Http\Controllers\Api\Mom\MeetingController`. Every route in this group carries `permission:meetings,{view|create|edit}` middleware (`EnsurePermission` + `RbacService`), on top of a second, finer-grained in-controller authorization layer (`RbacService::scopeFor()` for list visibility, `AuthorizesByScope::scopeCoversOwner()` for single-record edit/cancel/complete checks) that restricts non-`company`-scope users to meetings they created or participate in. A caller can pass the route-level `permission:` gate (has *some* grant on `meetings`) yet still be blocked by the in-controller scope check with a `200`-status `{success:false}` body — these are two distinct failure modes with two distinct status codes; see each endpoint's error table.

Meeting lifecycle: `scheduled` → `completed` **or** `scheduled` → `cancelled` (terminal states; no state transition out of `completed`/`cancelled` exists in this controller). `update()` and `complete()` only operate on meetings still in `scheduled` status; `cancel()` is blocked only once a meeting is already `completed` or `cancelled`.

## GET /meetings/
**Purpose:** List meetings visible to the authenticated user, with status/date/participation filters and pagination.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting` uses `TenantTrait` (auto-scoped to the JWT user's `tenant_id`). Within that tenant, visibility is further narrowed by RBAC scope (see below).
**Permissions/role requirements:** `permission:meetings,view` (route middleware) **plus** an in-controller scope check: `RbacService::scopeFor($authUser, 'meetings', 'view')` — if scope is `null` (no grant at all) returns `403`; if scope is anything other than `company`, the query is further restricted to meetings the user created or participates in.
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | Filter by `Meeting.status`. `all` (or omitted) disables the filter. |
| `date_from` | date | No | `meeting_date >= date_from` |
| `date_to` | date | No | `meeting_date <= date_to` |
| `my_meetings` | boolean | No | When truthy, restricts to meetings where the user is creator or participant (redundant with the non-company scope restriction, but applies even for `company`-scope users). |
| `sort_by` | string | No | `date_asc` or `date_desc` (default). Sorts by `meeting_date` then `start_time`. |
| `per_page` | int | No | Default `15`. |
| `page` | int | No | Standard paginator param. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/meetings/?status=scheduled&my_meetings=1&sort_by=date_asc" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meetings fetched successfully",
  "data": [
    {
      "id": 12,
      "meeting_id": "MT-000012",
      "title": "Sprint Planning",
      "description": "Plan next sprint",
      "meeting_date": "2026-10-02",
      "start_time": "10:00",
      "end_time": "11:00",
      "meeting_type": "virtual",
      "location": "Google Meet",
      "status": "scheduled",
      "cancellation_reason": null,
      "reminder_minutes_before": 15,
      "created_by": { "id": 3, "name": "Priya Sharma", "employee_id": "EMP003" },
      "participants": [
        {
          "id": 3,
          "name": "Priya Sharma",
          "employee_id": "EMP003",
          "email": "priya@example.com",
          "role": "organizer",
          "is_mom_writer": false
        }
      ],
      "participant_count": 1,
      "mom_writers": [
        { "id": 5, "name": "Rahul Verma", "employee_id": "EMP005", "email": "rahul@example.com" }
      ],
      "created_at": "2026-09-28 09:00:00",
      "updated_at": "2026-09-28 09:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "next_page_url": "https://vpshrms.shurttech.com/api/meetings/?page=2",
    "prev_page_url": null,
    "last_page": 3,
    "per_page": 15,
    "total": 34
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | Route middleware `permission:meetings,view` fails (no grant on module at all) | `{"message": "You do not have permission for this action."}` |
| 403 | In-controller: `RbacService::scopeFor()` returns `null` | `{"success": false, "message": "You do not have permission to view meetings."}` |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later.<exception message appended>"}` |

**Pagination:** Standard Laravel paginator, custom `pagination` object (no `total_count`/`links` array — different shape from the Notifications module's pagination block).
**Filters:** `status`, `date_from`, `date_to`, `my_meetings`, `sort_by`.
**Special behavior / notes:** `participants[].role` is `organizer` for the creator, `attendee` for everyone else. A meeting's own aggregate `stats` block (total/upcoming/today/completed/cancelled counts) is computed in the controller but currently commented out of the response payload — do not rely on it being present.

---

## GET /meetings/detail/{id}
**Purpose:** Fetch full details of a single meeting, including participants, MoM writers, and linked tasks.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting::find($id)` is tenant-scoped via `TenantTrait`; a meeting from another tenant simply won't be found.
**Permissions/role requirements:** `permission:meetings,view` (route middleware) plus an in-controller access check: the user must be the creator, a participant, **or** have `company`-scope on `meetings,view`.
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` (path) | int | Yes | Meeting's numeric `id`. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/meetings/detail/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meeting details fetched successfully",
  "data": {
    "id": 12,
    "meeting_id": "MT-000012",
    "title": "Sprint Planning",
    "description": "Plan next sprint",
    "meeting_date": "2026-10-02",
    "start_time": "10:00",
    "end_time": "11:00",
    "meeting_type": "virtual",
    "location": "Google Meet",
    "status": "scheduled",
    "cancellation_reason": null,
    "reminder_minutes_before": 15,
    "created_by": { "id": 3, "name": "Priya Sharma", "employee_id": "EMP003", "email": "priya@example.com" },
    "participants": [
      { "user_id": 3, "name": "Priya Sharma", "employee_id": "EMP003", "email": "priya@example.com", "role": "organizer", "is_mom_writer": false }
    ],
    "participant_count": 1,
    "mom_writers": [
      { "id": 5, "name": "Rahul Verma", "employee_id": "EMP005", "email": "rahul@example.com" }
    ],
    "tasks": [
      {
        "id": 88,
        "task_code": "TSK-000088",
        "title": "Prepare backlog",
        "priority": "high",
        "status": "in_progress",
        "deadline_date": "2026-10-05",
        "project_name": "HRM Mobile App",
        "assignees": [ { "id": 5, "name": "Rahul Verma" } ]
      }
    ],
    "task_count": 1,
    "created_at": "2026-09-28 09:00:00",
    "updated_at": "2026-09-28 09:00:00"
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | Route middleware `permission:meetings,view` fails | `{"message": "You do not have permission for this action."}` |
| 200 | Meeting not found (returned as success-status HTTP code with `success:false` body — **not a 404**) | `{"success": false, "message": "Meeting not found."}` |
| 200 | User has no access to this meeting (not creator/participant/company-scope) | `{"success": false, "message": "You do not have access to this meeting."}` |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Note the HTTP status quirk: "not found" and "no access" both return HTTP `200` with `success:false` in the body (this differs from `index()`'s `403` for scope-`null`, and from Laravel's default `404` for `findOrFail` misses elsewhere in this API — always check `success` in the body, never rely on status code alone for this module's single-record fetches). `tasks[]` reflects any `Task` rows linked to this meeting (meeting-to-task linkage, e.g. action items assigned during the meeting).

---

## POST /meetings/store
**Purpose:** Create a new meeting, its participant list, and its MoM writer assignment.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** New `Meeting` is created for the authenticated user's tenant (via `TenantTrait`'s creating-scope). `participants.*`/`mom_writers.*` are validated only against `exists:users,id` — **no explicit tenant check on the referenced user IDs** in this validator.
**Permissions/role requirements:** `permission:meetings,create`
**Headers:** See Common conventions. `Content-Type: application/json` (or form-encoded).
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | string | Yes | max 255 |
| `description` | string | No | nullable |
| `meeting_date` | date | Yes | must be `today` or later (`after_or_equal:today`) |
| `start_time` | string | Yes | `H:i` format (24-hour, e.g. `"14:30"`) |
| `end_time` | string | Yes | `H:i` format, must be `after:start_time` |
| `meeting_type` | string | Yes | one of `physical`, `virtual`, `hybrid` |
| `location` | string | Yes | max 255 |
| `participants` | array | Yes | min 1 item; each element `exists:users,id` |
| `participants.*` | int | Yes | user id |
| `mom_writers` | array | Yes | can be empty array; each element `exists:users,id`. If more than one id is supplied, only the **first** is actually kept as MoM writer (server silently truncates to one). |
| `mom_writers.*` | int | No | user id |
| `reminder_minutes` | int | No | 0–1440; defaults to `15` if omitted. Stored as `reminder_minutes_before`. |

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/meetings/store" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Sprint Planning",
    "description": "Plan next sprint",
    "meeting_date": "2026-10-02",
    "start_time": "10:00",
    "end_time": "11:00",
    "meeting_type": "virtual",
    "location": "Google Meet",
    "participants": [3, 5, 8],
    "mom_writers": [5],
    "reminder_minutes": 15
  }'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meeting created successfully"
}
```
Note: the response does **not** include the created meeting's `id`/`meeting_id` — callers must call `GET /meetings/` or `GET /meetings/detail/{id}` afterward to retrieve them.

**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,create` fails | `{"message": "You do not have permission for this action."}` |
| 200 | Validation failure (first error only) | `{"success": false, "message": "The end time must be a date after start time."}` |
| 500 | Unhandled exception (incl. DB failure — transaction rolled back) | `{"success": false, "message": "Failed to create meeting: <exception message>"}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** `meeting_id` is auto-generated as `MT-` + zero-padded 6-digit sequence based on the last `Meeting.id` in the whole table (`MT-000001`, `MT-000002`, ...) — **not tenant-scoped**, so the sequence is shared/global across tenants (a gap-prone, non-atomic `orderBy('id','desc')->first()` pattern — concurrent creates could theoretically collide, though `id` remains the real primary key). The authenticated user is auto-added to `participants` as `role=organizer` if not already included; every other listed participant gets `role=attendee`. `is_mom_writer` on each `MeetingParticipant` row is set from membership in the (possibly truncated) `mom_writers` array. On success, `MeetingNotificationService::notifyMeetingCreated()` and `notifyMomWriters()` fire (wrapped in their own try/catch — a notification failure never fails the create).

---

## POST /meetings/update/{id}
**Purpose:** Update a scheduled meeting's details and/or participant list.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting::find($id)` tenant-scoped via `TenantTrait`.
**Permissions/role requirements:** `permission:meetings,edit` (route) **plus** `AuthorizesByScope::scopeCoversOwner($authUser, 'meetings', 'edit', $meeting->created_by)` — the caller must either have `company`-scope edit rights, or (for narrower scopes such as `team`/`own`) the meeting's `created_by` must fall within their scope.
**Headers:** See Common conventions.
**Request parameters / body:** All fields are `sometimes` (only validated/applied if present in the request — partial update).

| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | string | sometimes | max 255 |
| `description` | string | No | nullable |
| `meeting_date` | date | sometimes | `after_or_equal:today` |
| `start_time` | string | sometimes | `H:i` |
| `end_time` | string | sometimes | `H:i`, `after:start_time` |
| `meeting_type` | string | sometimes | `physical`, `virtual`, or `hybrid` |
| `location` | string | sometimes | max 255 |
| `participants` | array | sometimes | min 1; each `exists:users,id`. If supplied, fully re-syncs the participant list (removes anyone no longer listed, adds/updates the rest). |
| `participants.*` | int | No | user id |
| `mom_writers` | array | No | nullable; used only if `participants` was also supplied in the same request. |
| `mom_writers.*` | int | No | user id |
| `reminder_minutes` | int | No | 0–1440; maps to `reminder_minutes_before` |

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/meetings/update/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{"title": "Sprint Planning (rescheduled)", "start_time": "11:00", "end_time": "12:00"}'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meeting updated successfully"
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,edit` route middleware fails | `{"message": "You do not have permission for this action."}` |
| 200 | Meeting not found | `{"success": false, "message": "Meeting not found."}` |
| 200 | Caller's scope doesn't cover this meeting's creator | `{"success": false, "message": "You are not authorized to edit this meeting."}` |
| 200 | Meeting status is not `scheduled` | `{"success": false, "message": "This meeting cannot be edited because it is {status}."}` |
| 200 | Validation failure | `{"success": false, "message": "<first validation error>"}` |
| 500 | Unhandled exception (transaction rolled back) | `{"success": false, "message": "Failed to update meeting: <exception message>"}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Editing is **only** allowed while `status == 'scheduled'` — completed/cancelled meetings are permanently locked from edits. Supplying `participants` fully re-syncs `MeetingParticipant` rows via diff (`array_diff` to find removals) + `updateOrCreate` for adds/updates; the creator is force-included even if omitted from the payload. `MeetingNotificationService::notifyMeetingUpdated()` fires only if the diff between old and new field values (`$changedFields`) is non-empty (excludes `updated_at` from the diff).

---

## POST /meetings/cancel/{id}
**Purpose:** Cancel a meeting, recording a mandatory reason.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting::find($id)` tenant-scoped via `TenantTrait`.
**Permissions/role requirements:** `permission:meetings,edit` (route) plus `AuthorizesByScope::scopeCoversOwner($authUser, 'meetings', 'edit', $meeting->created_by)`.
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `reason` | string | Yes | min 5, max 500 chars |

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/meetings/cancel/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Client rescheduled the project kickoff."}'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meeting cancelled successfully"
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,edit` route middleware fails | `{"message": "You do not have permission for this action."}` |
| 200 | Validation failure (reason missing/too short/too long) | `{"success": false, "message": "<first validation error>"}` |
| 200 | Meeting not found | `{"success": false, "message": "Meeting not found."}` |
| 200 | Caller's scope doesn't cover this meeting's creator | `{"success": false, "message": "You are not authorized to cancel this meeting."}` |
| 200 | Meeting already `completed` or `cancelled` | `{"success": false, "message": "Cannot cancel meeting that is already {status}."}` |
| 500 | Unhandled exception (transaction rolled back) | `{"success": false, "message": "Failed to cancel meeting: <exception message>"}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Sets `status = 'cancelled'` and stores `cancellation_reason`. Unlike `update()`/`complete()`, cancel is allowed from any non-terminal status (not just `scheduled` — e.g. no explicit block against cancelling a meeting that's mid-flight, only against re-cancelling an already-`completed`/`cancelled` one). Fires `MeetingNotificationService::notifyMeetingCancelled()` on success (non-blocking try/catch).

---

## POST /meetings/complete/{id}
**Purpose:** Mark a scheduled meeting as completed.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting::find($id)` tenant-scoped via `TenantTrait`.
**Permissions/role requirements:** `permission:meetings,edit` (route) plus `AuthorizesByScope::scopeCoversOwner($authUser, 'meetings', 'edit', $meeting->created_by)`.
**Headers:** See Common conventions.
**Request parameters / body:** None (no body fields read by this action).
**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/meetings/complete/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Meeting marked as completed successfully"
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,edit` route middleware fails | `{"message": "You do not have permission for this action."}` |
| 200 | Meeting not found | `{"success": false, "message": "Meeting not found."}` |
| 200 | Caller's scope doesn't cover this meeting's creator | `{"success": false, "message": "You are not authorized to complete this meeting."}` |
| 200 | Meeting status is not `scheduled` | `{"success": false, "message": "Meeting cannot be marked as completed because it is {status}."}` |
| 500 | Unhandled exception (transaction rolled back) | `{"success": false, "message": "Failed to complete meeting: <exception message>"}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Only reachable from `scheduled` — a `cancelled` meeting can never transition to `completed`. This is a terminal-state transition; no undo endpoint exists. No notification service call is made for completion (unlike create/update/cancel).

---

## POST /meetings/attendance/{id}
**Purpose:** Record the authenticated user's own RSVP/attendance response for a meeting they participate in.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting::find($request->meeting_id)` tenant-scoped via `TenantTrait`; `MeetingParticipant` lookup is additionally filtered to `user_id = $authUser->id`, so this is inherently self-scoped — a user can never set another participant's attendance.
**Permissions/role requirements:** `permission:meetings,view` (note: this is the *view* permission, not `edit` — updating your own RSVP is treated as a read-level action in this codebase).
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `{id}` (path) | int | Yes | Present in the route but **not read by the controller** — the actual meeting is resolved from the `meeting_id` body field instead (see notes). |
| `meeting_id` | int | Yes | `exists:meetings,id`. This, not the path `{id}`, determines which meeting is updated. |
| `attendance` | string | Yes | one of `confirmed`, `declined`, `tentative` |
| `comments` | string | No | free text, stored as `response_comments`; nullable |

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/meetings/attendance/12" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{"meeting_id": 12, "attendance": "confirmed", "comments": "Will join 5 minutes late"}'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Attendance updated successfully"
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,view` route middleware fails | `{"message": "You do not have permission for this action."}` |
| 200 | Validation failure (bad `attendance` value, missing/invalid `meeting_id`) | `{"success": false, "message": "<first validation error>"}` |
| 200 | Meeting (by `meeting_id`) not found | `{"success": false, "message": "Meeting not found."}` |
| 200 | Caller has no `MeetingParticipant` row for this meeting | `{"success": false, "message": "You are not a participant of this meeting."}` |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to update attendance: <exception message>"}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** **Important client-integration caveat**: the path `{id}` segment is not actually used to resolve the meeting — always send the correct `meeting_id` in the request body too, or the wrong (or no) meeting record will be updated even though the URL looks correct. Updates `attendance_status`, `response_comments`, and `responded_at = now()` on the caller's own `MeetingParticipant` row. Can be called repeatedly to change a prior RSVP (no lock after first response).

---

## GET /meetings/mom-writer/meetings
**Purpose:** List meetings for which the authenticated user is assigned as a Minutes-of-Meeting writer.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `Meeting` tenant-scoped via `TenantTrait`; further filtered via `whereHas('momWriters', ...)` to only meetings where the user is listed as a MoM writer.
**Permissions/role requirements:** `permission:meetings,view`
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | Filter by `Meeting.status`. `all` (or omitted) disables the filter. |
| `per_page` | int | No | Default `15`. |
| `page` | int | No | Standard paginator param. |

**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/meetings/mom-writer/meetings?status=scheduled" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "MOM writer meetings fetched successfully",
  "data": [
    {
      "id": 12,
      "meeting_id": "MT-000012",
      "title": "Sprint Planning",
      "meeting_date": "2026-10-02",
      "start_time": "10:00",
      "status": "scheduled",
      "participant_count": 3
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 403 | `permission:meetings,view` route middleware fails | `{"message": "You do not have permission for this action."}` |
| 500 | Unhandled exception | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Pagination:** Standard Laravel paginator, minimal custom `pagination` object (no `next_page_url`/`prev_page_url` fields here, unlike `index()`).
**Filters:** `status`.
**Special behavior / notes:** Always excludes `cancelled` meetings regardless of the `status` filter value (`->where('status', '!=', 'cancelled')` is applied unconditionally before the optional status filter). This is the mobile equivalent of the web MoM-writer worklist — used by whoever is assigned to write minutes to find their pending meetings.

---

## Offboarding

Controller: `App\Http\Controllers\Api\Offboarding\offboardingController` (file has a lowercase leading `o` in its class/file name; `routes/api.php` imports it aliased as `OffboardingController` — functionally identical, just be aware of the case mismatch if grepping the codebase). This surface is **employee-self-service only** — every action scopes to `Auth::id()` as the `employee_id`; there is no way to view or act on another employee's offboarding request through this API. None of these routes carry `permission:` middleware (unlike Meetings) — any authenticated tenant user can call them for their own record. Per `docs/modules.md`, `store()` is hardcoded to `reason = 'resignation'` — the mobile API does not support termination/retirement/other reasons (those remain web/HR-only flows via the generic `ApprovalService`-backed web offboarding controller).

## GET /offboarding/noticePeriode
**Purpose:** Return the tenant's required notice period (in days), whether the caller already has an active offboarding request, and the earliest allowable last working date.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `tenantId` is read from `session('tenant_id')` (not directly from the JWT claim) and passed into `OffboardingService::requiredNoticeDays($tenantId)`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/offboarding/noticePeriode" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Notice period information retrieved successfully",
  "data": {
    "notice_period_days": 30,
    "is_applied": false,
    "min_last_working_date": "2026-10-28"
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to retrieve notice period information."}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** `notice_period_days` resolves from `tenants.notice_period` if set, else falls back to `config('offboarding.default_notice_period_days')` (30 by default) — see `OffboardingService::requiredNoticeDays()`. `is_applied` is `true` if the employee already has a request in `pending_approval`, `approved`, or `completed` status (used by the mobile UI to disable/hide the "submit resignation" action). `min_last_working_date` is simply `today + notice_period_days` — a convenience precomputed value for pre-filling the date picker; the server still independently re-validates this floor on `store()`.

---

## GET /offboarding/my-requests
**Purpose:** List all of the authenticated employee's own offboarding request records (history), plus a summary of any currently active one.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `OffboardingRequest` uses `TenantTrait`; additionally hard-filtered to `employee_id = Auth::id()`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/offboarding/my-requests" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Offboarding requests retrieved successfully",
  "data": {
    "requests": [
      {
        "id": 7,
        "request_code": "OFB-000007",
        "status": "pending_approval",
        "status_label": "Pending Approval",
        "reason": "resignation",
        "reason_detail": "Relocating to another city.",
        "request_date": "2026-09-28",
        "last_working_date": "2026-10-28",
        "manager_review_status": "pending",
        "hr_review_status": null,
        "knowledge_transfer_status": null,
        "asset_return_status": null,
        "exit_interview_status": null,
        "final_settlement_status": null,
        "created_at": "2026-09-28T09:12:00.000000Z",
        "current_stage": "Pending Manager Approval"
      }
    ],
    "has_active_request": true,
    "active_request": {
      "id": 7,
      "request_code": "OFB-000007",
      "status": "pending_approval",
      "last_working_date": "2026-10-28"
    }
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to retrieve offboarding requests."}` |

**Pagination:** None — all matching requests are returned unpaginated in a single `get()` call.
**Filters:** None
**Special behavior / notes:** `current_stage` is a derived human-readable label computed by the private `getCurrentStage()` helper (walks `status`/`final_settlement_status`/`asset_return_status`/`knowledge_transfer_status`/`hr_review_status`/`manager_review_status` in priority order — see controller lines 499–532). `active_request` is only populated when a request exists with status `pending_approval` or `approved` (an already-`completed`/`cancelled`/`rejected` request never appears here even if it's the most recent one).

---

## GET /offboarding/show
**Purpose:** Get the authenticated employee's single most recent offboarding request, formatted as a status/timeline view (dedicated "My Resignation Status" screen).
**Auth:** JWT bearer + singleLogin
**Tenant scope:** Filtered to `employee_id = Auth::id()`; tenant for the notice-period lookup comes from `session('tenant_id')`.
**Permissions/role requirements:** None
**Headers:** See Common conventions.
**Request parameters / body:** None (no identifying parameter — always returns the caller's own latest request by `id DESC`; there is no way to specify a particular historical request id on this endpoint).
**File uploads:** None
**Sample request:**
```bash
curl -X GET "https://vpshrms.shurttech.com/api/offboarding/show" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>"
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "data fetched Successfully.",
  "data": {
    "resignation_status": "manager_review",
    "notice_period_days": 30,
    "resignation_date": "2026-09-28",
    "last_working_date": "2026-10-28",
    "reason": "Resignation",
    "reason_detail": "Relocating to another city.",
    "timeline": [
      { "status": "notice_submission", "title": "Notice Submission", "subtitle": "Request Submitted", "completed": true },
      { "status": "manager_review", "title": "Manager Review", "subtitle": "Pending Approval", "completed": false },
      { "status": "hr_review", "title": "HR Review", "subtitle": "Pending Manager Approval First", "completed": false },
      { "status": "approval", "title": "Approval & Date Lock", "subtitle": "Waiting for Approvals", "completed": false },
      { "status": "handover", "title": "Knowledge Transfer", "subtitle": "Not Started", "completed": false },
      { "status": "clearance", "title": "Asset Clearance", "subtitle": "Pending", "completed": false },
      { "status": "exit_interview", "title": "Exit Interview", "subtitle": "Not Scheduled", "completed": false },
      { "status": "settlement", "title": "Final Settlement", "subtitle": "Locked", "completed": false }
    ]
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 200 | Employee has no offboarding request at all | `{"success": false, "message": "Offboarding request not found."}` |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to retrieve offboarding details."}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** `notice_period_days` here comes straight from `Tenant.notice_period` (defaulting to `30` inline if null) — a slightly different code path than `noticePeriode()`'s `OffboardingService::requiredNoticeDays()`, though both should normally agree since that service reads the same column with the same fallback constant. `resignation_status` is a derived single-token state computed by `getResignationStatus()` — a different, coarser vocabulary than `status`/`current_stage` used elsewhere (possible values: `completed`, `rejected`, `cancelled`, `settlement_completed`, `settlement_processing`, `clearance_completed`, `handover_completed`, `handover_in_progress`, `hr_approved`, `hr_rejected`, `manager_approved`, `manager_rejected`, `manager_review`, `initiated`). `timeline[].completed` is monotonic — a stage is only ever marked completed if **every earlier stage** in the fixed 8-stage sequence is also completed, even if that later stage's own underlying status flag looks satisfied out of order. Per `docs/modules.md`, these status fields (`manager_review_status`, `hr_review_status`, `asset_return_status`, etc.) are legacy mirror columns kept in sync by `OffboardingService` specifically so this unmigrated mobile timeline keeps working — the real source of truth on the web/approval side is `approval_requests`/`approval_actions` and `offboarding_clearance_tasks`.

---

## POST /offboarding/store
**Purpose:** Submit a new resignation (offboarding) request for the authenticated employee.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** New `OffboardingRequest` created under the caller's tenant via `OffboardingService::submit()`.
**Permissions/role requirements:** None at the route/middleware level; `OffboardingService::submit()` internally checks `config('offboarding.reason_rules.resignation.creatable_by')` against the actor's `role` and throws a `RuntimeException` (surfaced as `200 {success:false}`) if the role isn't permitted to self-submit a resignation.
**Headers:** See Common conventions. `Content-Type: application/json`.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `last_working_date` | date | Yes | `after_or_equal:today`. Also independently re-validated server-side against the tenant's notice-period floor inside `OffboardingService::submit()` (see notes). |
| `resignation_date` | date | No | nullable, `before_or_equal:today`. Defaults to `now()->toDateString()` if omitted. |
| `reason_detail` | string | Yes | min 10, max 500 chars |
| `feedback` | string | No | nullable, max 1000 chars |

Note: `reason` is **not** a client-supplied field — the controller always hardcodes it to `'resignation'` before calling the service.

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/offboarding/store" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "last_working_date": "2026-10-28",
    "reason_detail": "Relocating to another city for personal reasons.",
    "feedback": "Great experience working here."
  }'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Offboarding request submitted successfully!",
  "data": {
    "id": 7,
    "request_code": "OFB-000007",
    "status": "pending_approval",
    "last_working_date": "2026-10-28"
  }
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 200 | Request validation failure | `{"success": false, "message": "<first validation error>"}` |
| 200 | Business-rule failure raised as `RuntimeException` (already has an active request; `last_working_date` shorter than the required notice period; employee already inactive; role not allowed to self-submit) | `{"success": false, "message": "This employee already has an active offboarding request."}` (example — message varies by which rule failed) |
| 500 | Unhandled exception (any other failure) | `{"success": false, "message": "Failed to create offboarding request."}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** Business rules enforced inside `OffboardingService::submit()` beyond the request-level validator: (1) rejects if the employee already has a request in `pending_approval` or `approved` status ("This employee already has an active offboarding request."); (2) rejects if `last_working_date` is earlier than `today + requiredNoticeDays(tenant)` ("Last working date must be at least {N} day(s) from today ({date})."); (3) rejects if the employee's `User.status` is already `0` (inactive). On success, a database trigger (`generate_offboarding_request_code`, per `docs/modules.md`) fills `request_code` (`OFB-######`-style); the new request starts at `status = pending_approval` and feeds into the tenant's generic `ApprovalService` workflow for `offboarding` (2-level Manager → HR by default) — this mobile endpoint does not expose any way to configure or bypass that workflow.

---

## POST /offboarding/cancel/{id}
**Purpose:** Cancel the authenticated employee's own offboarding request while it is still cancellable.
**Auth:** JWT bearer + singleLogin
**Tenant scope:** `OffboardingRequest` looked up filtered to `employee_id = Auth::id()` — no separate explicit `tenant_id` check needed since ownership by the JWT user already implies same-tenant (the model's own `TenantTrait` global scope also applies on top of this).
**Permissions/role requirements:** None at the route level (self-service only, enforced by the `employee_id = Auth::id()` filter).
**Headers:** See Common conventions.
**Request parameters / body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` (path) | int | Yes | The `OffboardingRequest.id` to cancel. Must belong to the caller and currently be in `pending_approval` or `approved` status. |
| `cancellation_reason` | string | No | Free text. Defaults to `"Cancelled by employee"` if omitted. |

**File uploads:** None
**Sample request:**
```bash
curl -X POST "https://vpshrms.shurttech.com/api/offboarding/cancel/7" \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device_token>" \
  -H "Content-Type: application/json" \
  -d '{"cancellation_reason": "Decided to stay with the company."}'
```
**Success response:** `200 OK`
```json
{
  "success": true,
  "message": "Offboarding request cancelled successfully."
}
```
**Error responses:**

| HTTP status | Condition | Example body |
|---|---|---|
| 401 | Missing/invalid JWT or Device-Token mismatch | See Common conventions |
| 404 | No matching request for this employee with `id` in status `pending_approval`/`approved` (covers "not found" and "not owned by caller" and "already in a terminal state" — all collapse to the same 404) | `{"success": false, "message": "Offboarding request not found or cannot be cancelled."}` |
| 200 | `OffboardingService::cancel()` raises a `RuntimeException` (e.g. request already completed/cancelled/rejected — reached only if the initial lookup's `whereIn` somehow passed but the service's own guard still catches a race) | `{"success": false, "message": "This request cannot be cancelled from its current status."}` |
| 500 | Unhandled exception | `{"success": false, "message": "Failed to cancel offboarding request."}` |

**Pagination:** None
**Filters:** None
**Special behavior / notes:** This is the one Offboarding endpoint that returns a real `404` (not `200 {success:false}`) for "not found" — inconsistent with `show()`'s `200`-status "not found" response elsewhere in this same controller; do not assume a uniform not-found convention across this module. Only requests in `pending_approval` or `approved` status can be cancelled — once a request has moved further (`completed`, already `cancelled`, or `rejected`) it is permanently locked from self-cancellation via this endpoint. On cancel, `OffboardingService::cancel()` transactionally: locks the row (`lockForUpdate()`), cancels any still-`pending` `ApprovalRequest` tied to it, sets `status = cancelled`, `current_stage = cancelled`, stamps `cancelled_by`/`cancelled_at`/`cancelled_reason`, writes an `AuditLogger` entry (`offboarding.cancelled`), and fires a (non-blocking) cancellation notification.
