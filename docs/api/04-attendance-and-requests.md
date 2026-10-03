# Attendance, Travel Requests & Overtime API

Covers GPS-based clock-in/out, attendance history, live location tracking, attendance regularization, travel/WFH requests, and overtime requests — all part of the mobile/legacy JSON API (`routes/api.php`), consumed by the Flutter app. Base URL: `https://vpshrms.shurttech.com`.

**Auth for every endpoint in this module:** JWT bearer token AND a matching `Device-Token` header (single-device-login enforcement). Tenant is resolved solely from the JWT user's `tenant_id` — never from a client-supplied header on these routes.

- `Authorization: Bearer <jwt>` — required. Missing/invalid/expired/blacklisted token → `401 {"message":"Unauthenticated."}`
- `Device-Token: <token>` — required, must equal `users.last_login_token` (set at login). Mismatch → `401 {"status":false,"message":"Your account is logged in on another device. Please login again."}`

This API has **no fixed response envelope** — every controller returns its own ad hoc JSON shape (`status` or `success` boolean, `message`, sometimes `data`). Shapes below are taken verbatim from the controller code, not normalized.

Common 500 body across this module: `{"status": false, "message": "An error occured. Please try again later."}` (or `{"success": false, "message": "..."}` — varies by action; the exact key is called out per endpoint).

---

## Attendance

Controller: `app/Http/Controllers/Api/Attendance/AttendanceController.php`

### POST /api/user/attendance/clock-in
**Purpose:** Record a GPS-verified clock-in for the current user, starting an attendance punch session.
**Auth:** JWT + Device-Token.
**Tenant scope:** Current user's tenant.
**Permission/role required:** None (any authenticated employee).
**Headers:** `Authorization`, `Device-Token`, `Content-Type: application/json`.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `lat` | numeric | Yes | Latitude. |
| `long` | numeric | Yes | Longitude. |
| `address` | string | Yes | 5–255 chars, reverse-geocoded address shown in logs/UI. |
| `battery_per` | any | No | Device battery percentage, stored as-is. |
| `device_id` | string | No | |
| `wifi_ssid` | string | No | |
| `network_type` | string | No | |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/user/attendance/clock-in \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: <device-token>" \
  -H "Content-Type: application/json" \
  -d '{"lat":28.6139,"long":77.2090,"address":"Connaught Place, New Delhi","battery_per":82,"device_id":"pixel-7","network_type":"wifi"}'
```

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Clock-In successful.",
  "tracking_enabled": true,
  "next_ping_seconds": 60,
  "shift": {"user_shift_id": 812, "shift_id": 3, "name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "is_additional": false}
}
```
(`shift` — added 2026-10-02 — is the shift this clock-in counted for; `null` for a company on one fixed shift or when no shift applies.)

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | User record not found | `{"status": false, "message": "User not found."}` |
| 200 | No configured/geofenced branch for "any branch" employees | `{"status": false, "message": "No branches configured in the system. Please contact admin."}` |
| 200 | Not within radius of any branch | `{"status": false, "message": "You are not within the allowed radius of any branch. Please move closer to your office location to clock in."}` |
| 200 | Assigned branch missing/unconfigured | `{"status": false, "message": "Branch not configured. Please contact admin."}` or `"Attendance location coordinates not configured. Please contact admin."` |
| 200 | Outside assigned-branch radius | `{"status": false, "message": "You are {distance} meters away from your assigned branch '{name}'. Maximum allowed distance for clock in is {radius} meters. Please move closer to the office to clock in."}` |
| 200 | Already an open punch session | `{"status": false, "message": "<OpenPunchSessionException message>"}` |
| 200 | Attendance period locked (payroll closed) | `{"status": false, "message": "<PeriodLockedException message>"}` |
| 500 | Punch-capture service threw, or unhandled exception | `{"status": false, "message": "Clock-in failed: ..."}` or generic 500 body |

**Special behavior / notes:**
- Geofencing: if the employee's `job_details.type == 'office'` and `office_branch == 0`, the nearest active + geofenced `AttendanceLocation` within its radius (default 50m) is auto-selected; if `office_branch` names a specific branch, that branch's geofence (if enabled) is enforced.
- Geofencing/location check is **skipped entirely** if the user has an `APPROVED` travel/WFH `Request` covering the attendance date (`hasApprovedRequest`).
- Night-shift aware: the actual "attendance date" used is resolved by `AttendanceCalculator::resolveAttendanceDate()`, which can roll back to the previous calendar day for overnight shifts (a shift's `is_overnight` flag decides).
- Multi-shift (2026-10-02): for an employee with a 2nd+ ("additional") shift that day or a day either side, the clock-in is matched to the shift whose window `[start − 2h, end)` contains it and takes that shift's date. After finishing one shift, a clock-in for another shift not yet started that day is accepted even when the company does not allow multiple punches.
- On success, also starts/updates a `TrackingSessionService` session and ingests one field-tracking GPS point; triggers a push notification (`AttendanceNotificationService::notifyClockIn`) — notification failures are swallowed and logged, never surfaced to the client.
- `tracking_enabled`/`next_ping_seconds` in the response tell the mobile app whether to run the background GPS tracker and at what interval (from `FieldTrackingService::resolveForUser`).
- Every attempt (success or failure) writes an `AttendanceLog` audit row with device/network/GPS metadata.

---

### POST /api/user/attendance/clock-out
**Purpose:** Record GPS-verified clock-out, closing the day's open punch session.
**Auth:** JWT + Device-Token. **Tenant scope:** current user. **Permission:** none.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `lat` | numeric | Yes | |
| `long` | numeric | Yes | |
| `address` | string | Yes | 5–255 chars |
| `accuracy` | numeric | No | 0–100 |
| `battery_per` | any | No | |
| `device_id` | string | No | |
| `wifi_ssid` | string | No | |
| `network_type` | string | No | |

**File uploads:** None.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/user/attendance/clock-out \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" -H "Content-Type: application/json" \
  -d '{"lat":28.6139,"long":77.2090,"address":"Connaught Place, New Delhi","accuracy":12.5}'
```

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Clock-Out successfully.",
  "data": {
    "clock_out_time": "2026-09-28 18:32:10",
    "total_hours": "8.53",
    "worked_hours": 8.53,
    "attendance_status": "Present",
    "shift_status": "complete",
    "shift": {"user_shift_id": 812, "shift_id": 3, "name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "is_additional": false}
  },
  "tracking_enabled": false,
  "next_ping_seconds": 0
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | No open check-in found (today or yesterday, night-shift aware) | `{"status": false, "message": "You have not checked in for any active shift."}` |
| 200 | Already checked out | `{"status": false, "message": "You have already checked out for this shift."}` |
| 200 | User not found | `{"status": false, "message": "User not found."}` |
| 200 | Same branch/geofence failures as clock-in (message text says "clock out") | see clock-in table |
| 200 | `NoOpenPunchSessionException` / `PeriodLockedException` from the punch service | `{"status": false, "message": "<exception message>"}` |
| 500 | Transaction/unhandled error | `{"status": false, "message": "An error occured. Please try again later."}` |

**Special behavior / notes:** Same geofencing/branch logic and approved-request bypass as clock-in. Also stops the field-tracking session (`tracking_enabled: false` always on success) and sends `notifyClockOut`.

---

### GET /api/user/attendance/history
**Purpose:** Day-by-day attendance/leave/holiday/week-off calendar for the current user over a date range.
**Auth:** JWT + Device-Token. **Tenant scope:** current user, tenant-scoped holiday lookup. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `start_date` | string `Y-m-d` | No | Defaults to first day of the month 30 days ago. Invalid format silently falls back to default. |
| `end_date` | string `Y-m-d` | No | Defaults to `Y-m-28` of a month 30 days ahead. Invalid format falls back to default. Range is auto-swapped if `start_date > end_date`, and capped at 366 days. |

**Sample request:**
```bash
curl "https://vpshrms.shurttech.com/api/user/attendance/history?start_date=2026-09-01&end_date=2026-09-30" \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>"
```

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Attendance data fetched successfully for current user",
  "data": [
    {
      "user_id": 101,
      "name": "Asha Rao",
      "email": "asha@example.com",
      "date": "2026-09-28",
      "day_name": "Monday",
      "is_future_date": 0,
      "task_count": 2,
      "clock_in": "2026-09-28 09:58:00",
      "clock_out": "2026-09-28 18:32:10",
      "total_hours": "8.53",
      "worked_hours": "8.53",
      "scheduled_shift_start": "09:30:00",
      "scheduled_shift_end": "18:30:00",
      "leave_type": null,
      "leave_reason": null,
      "leave_session": null,
      "holiday_name": null,
      "message": null,
      "week_off": null,
      "day_status": "Present"
    }
  ]
}
```
`day_status` values: `Present`, `Half Day`, `Absent`, `Checked In Only`, `Holiday`, `First Half Leave`, `Second Half Leave`, `Full Day Leave`, `Week Off`, `Upcoming`.

**Error responses:** `500 {"status": false, "message": "An error occured. Please try again later."}`.

**Special behavior / notes:** Built from a raw recursive-CTE SQL query (dates are validated against `^\d{4}-\d{2}-\d{2}$` before inlining — safe from injection; everything else is bound). Status priority: attendance record → holiday → leave → week-off → absent, with shift-based present/half-day/absent thresholds (< 20% of expected shift hours = Absent, 20–60% = Half Day, ≥ 60% = Present; falls back to a flat 2h/6h rule when no shift/worked-hours data exists).

---

### POST /api/user/attendance/track
**Purpose:** Single-ping GPS location update while clocked in (field tracking).
**Auth:** JWT + Device-Token. **Middleware:** `throttle:location-ingest` (40/min per user by default, configurable via `location.throttle_per_minute`). **Permission:** none.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `lat` | required (no type rule) | Yes | |
| `long` | required (no type rule) | Yes | |
| `address` | string | Yes | 5–255 chars |
| `battery_per` | any | No | |

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/user/attendance/track \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" -H "Content-Type: application/json" \
  -d '{"lat":28.6140,"long":77.2091,"address":"Near CP Metro","battery_per":75}'
```

**Success response — `200`:**
```json
{"status": true, "message": "Location saved successfully.", "tracking_enabled": true, "next_ping_seconds": 60}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Field tracking not enabled for user and `location.enforce_enabled` is on | `{"status": false, "message": "Location tracking is not enabled for your account.", "tracking_enabled": false, "next_ping_seconds": 0}` |
| 200 | No active clocked-in session | `{"status": false, "message": "User has not clocked in for any active shift.", "tracking_enabled": ..., "next_ping_seconds": 0}` |
| 200 | Already clocked out for the shift | `{"status": false, "message": "You have already checked out for this shift.", ...}` |
| 429 | Rate limit exceeded | `{"status": false, "message": "Too many location updates. Slow down.", "tracking_enabled": true, "next_ping_seconds": <config('location.default_ping_seconds', 60)>}` |
| 500 | Unhandled exception | `{"status": false, "message": "An error occured. Please try again later"}` |

**Special behavior / notes:** Internally routed through the same session-aware, idempotent batch-ingest path as `track-batch` (a synthetic 1-point batch with a server-generated `point_id`).

---

### POST /api/user/attendance/track-batch
**Purpose:** Buffered/batched GPS upload — app accumulates points locally and flushes periodically.
**Auth:** JWT + Device-Token. **Middleware:** `throttle:location-ingest`. **Permission:** none.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `points` | array | Yes | 1 to `config('location.batch_max', 60)` items. |
| `points[].point_id` | string | No | Max 64 chars — optional idempotency key. If omitted/empty, the server derives `sha1(lat\|long\|track_time)` (coords to 7 decimals, time as epoch second), so re-sending the same readings is deduplicated either way by a DB unique constraint. The app must keep each point's original `track_time` on retries. |
| `points[].lat` | numeric | Yes | |
| `points[].long` | numeric | Yes | |
| `points[].accuracy_meters` | numeric | No | 0–1000 |
| `points[].battery_per` | any | No | |
| `points[].address` | string | No | Max 255 |
| `points[].track_time` | required (any format) | Yes | |

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/user/attendance/track-batch \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" -H "Content-Type: application/json" \
  -d '{"points":[{"lat":28.61,"long":77.20,"track_time":"2026-09-28T10:00:00Z"},{"point_id":"uuid-2","lat":28.611,"long":77.201,"track_time":"2026-09-28T10:05:00Z"}]}'
```

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Saved 2 of 2 points.",
  "data": {"saved": 2, "rejected": 0, "duplicates": 0, "session_id": 4821},
  "tracking_enabled": true,
  "next_ping_seconds": 60
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Tracking not enabled + enforcement on | `{"status": false, "message": "Location tracking is not enabled for your account.", "tracking_enabled": false, "next_ping_seconds": 0}` |
| 200 | No active session to attach points to | `{"status": false, "message": "No active shift to attach location to.", ...}` |
| 429 | Rate limited | Same body as `/track`. |
| 500 | Unhandled | `{"status": false, "message": "An error occured. Please try again later"}` |

---

### GET /api/user/attendance/today
**Purpose:** Current user's today attendance snapshot + shift info, for the mobile home screen.
**Auth:** JWT + Device-Token. **Permission:** none. **Query params:** none.

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Data fetch successfully!!!",
  "data": {
    "id": 4821,
    "date": "2026-09-28",
    "clock_in": "2026-09-28 09:58:00",
    "clock_out": "",
    "clock_in_lat": "28.6139",
    "clock_in_long": "77.2090",
    "clock_in_address": "Connaught Place, New Delhi",
    "clock_out_lat": "",
    "clock_out_long": "",
    "clock_out_address": "",
    "total_hours": "",
    "status": 0,
    "user_name": "Asha Rao",
    "profile_image": "https://vpshrms.shurttech.com/uploads/profiles/asha.jpg",
    "attendance_type": "manual_attendance",
    "user_email": "asha@example.com",
    "designation": "Software Engineer",
    "shift": "General Shift (09:30 AM - 06:30 PM)",
    "location_tracking": {"enabled": true, "ping_seconds": 60},
    "shifts": [
      {"user_shift_id": 812, "shift_id": 3, "name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "is_overnight": false, "is_additional": false, "status": "ongoing", "clock_in": "2026-09-28 09:58:00", "clock_out": null, "worked_minutes": 0},
      {"user_shift_id": 913, "shift_id": 7, "name": "Night Shift", "start_time": "10:00 PM", "end_time": "06:00 AM", "is_overnight": true, "is_additional": true, "status": "upcoming", "clock_in": null, "clock_out": null, "worked_minutes": 0}
    ]
  }
}
```
(`shift` is `"Week Off"` on a weekoff day, or `"Shift not defined"` if no shift resolves — it always describes the day's main shift. `shifts` — added 2026-10-02 — lists every shift today, main first; `status` is `upcoming | ongoing | completed`. One item for a one-shift day.)

**Error responses:** `200 {"status": false, "message": "User not found"}`; `500 {"status": false, "message": "An error occured. Please try again later."}`.

**Special behavior / notes:** Night-shift aware — if there's no attendance row for today but yesterday's row has a null `clock_out`, that open session is returned instead.

---

### GET /api/user/attendance/today-locations
**Purpose:** GPS breadcrumb trail captured for the current user's attendance record today.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{
  "status": true,
  "message": "Data fetch successfully!!!",
  "data": [
    {"track_time": "2026-09-28 10:00:00", "lat": "28.6100", "long": "77.2000", "address": "Near CP Metro"}
  ]
}
```

**Error responses:** `200 {"status": false, "message": "No attendance record found for today."}`; `500 {"status": false, "message": "Failed to load location history."}`.

---

### GET /api/user/attendance/punches
**Purpose:** Raw punch list + paired in/out sessions for one day (default today) — supports multi-session days.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | string `Y-m-d` | No | Defaults to today; invalid format silently falls back to today. |

**Success response — `200`:**
```json
{
  "status": true,
  "data": {
    "date": "2026-09-28",
    "punches": [
      {"id": 991, "direction": "in", "punched_at": "2026-09-28 09:58:00", "source": "mobile_app", "method": "gps", "lat": 28.6139, "long": 77.209, "address": "Connaught Place"}
    ],
    "sessions": [
      {"clock_in": "2026-09-28 09:58:00", "clock_out": "2026-09-28 18:32:10", "worked_hours": 8.57,
       "shift": {"user_shift_id": 812, "shift_id": 3, "name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "is_additional": false}}
    ],
    "open_session": null,
    "session_count": 1,
    "shifts": [ "...same items as GET /api/user/attendance/today → shifts..." ]
  }
}
```
(`sessions[].shift` and `shifts` added 2026-10-02.)

**Error responses:** `500 {"status": false, "message": "An error occured. Please try again later."}`.

---

### GET /api/user/attendance/current-session
**Purpose:** Cheap "am I clocked in right now" check for the mobile clock-in/out button state.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{
  "status": true,
  "data": {
    "clocked_in": true,
    "session_number": 1,
    "clock_in_at": "2026-09-28 09:58:00",
    "open_since_seconds": 3600
  }
}
```
(`session_number`, `clock_in_at`, `open_since_seconds` are `null` when `clocked_in` is `false`.)

**Error responses:** `500 {"status": false, "message": "An error occured. Please try again later."}`.

---

### POST /api/user/attendance/regularization
**Purpose:** Submit a correction request for a past attendance day (missed punch, wrong time, WFH not marked, etc.).
**Auth:** JWT + Device-Token. **Permission:** none.
**Content-Type:** `multipart/form-data` if attaching a file, else `application/json`.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | string `Y-m-d` | Yes | Must be today or earlier. |
| `request_type` | string | Yes | One of `in_time,out_time,both,full_day,wfh_not_marked,technical_issue`. |
| `in_time` | string `H:i` | Conditional | Required if `request_type` is `in_time` or `both`. |
| `out_time` | string `H:i` | Conditional | Required if `request_type` is `out_time` or `both`. Must be after `in_time` — **except** for an overnight (night) shift, where an earlier out time means the next morning (e.g. in `22:00`, out `06:00`). |
| `user_shift_id` | integer | No | Added 2026-10-02. Which of the day's shifts the request corrects — a `user_shift_id` from `GET /api/user/attendance/today` → `shifts`. Omit for the main shift. Must be one of the employee's shifts on `date`. |
| `reason` | string | Yes | Max 500 chars. |
| `file` | file | No | `jpg,jpeg,png,pdf,doc,docx`, max 2048 KB. |

**File uploads:** field `file`; stored on the `public` disk under `attendance_files/`.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/user/attendance/regularization \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" \
  -F "date=2026-09-25" -F "request_type=in_time" -F "in_time=09:30" -F "reason=Forgot to clock in" -F "file=@proof.jpg"
```

**Success response — `200`:** `{"success": true, "message": "Request submitted successfully"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Out time not after in time on a non-overnight shift | `{"success": false, "message": "Out time must be after in time"}` |
| 200 | `user_shift_id` is not one of the employee's shifts that day | `{"success": false, "message": "Choose one of your shifts on this date."}` |
| 200 | Duplicate request for same date + type (+ shift) | `{"success": false, "message": "Request already exists for this date."}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

---

### GET /api/user/attendance/view-regularization
**Purpose:** List the current user's own regularization requests, paginated, with a status summary.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters (filters):**
| Field | Notes |
|---|---|
| `status` | `pending`/`approved`/`rejected`, or `all` (default: no filter). |
| `request_type` | one of the request-type enum values, or `all`. |
| `search` | matches `reason`, `date`, `request_type` (LIKE). |
| `start_date` / `end_date` | inclusive date range on `date`. |
| `per_page` | default 15. |

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Regularization requests fetched successfully",
  "data": [
    {
      "id": 55, "date": "2026-09-25", "request_type": "in_time", "in_time": "09:30", "out_time": null,
      "reason": "Forgot to clock in", "file": null, "status": "pending", "submit_date": "2026-09-25 20:11:03",
      "user_id": 101, "employee_id": "EMP-101", "user_name": "Asha Rao", "user_email": "asha@example.com",
      "profile_image": null, "designation": "Software Engineer", "approved_by": null, "approved_date": null
    }
  ],
  "summary": {"total": 4, "pending": 1, "approved": 2, "rejected": 1},
  "links": {"first": "...", "last": "...", "prev": null, "next": null}
}
```

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later."}`.

---

### GET /api/manager/attendance/view-regularization
**Purpose:** List regularization requests submitted by the caller's direct reportees (manager/admin approval queue).
**Auth:** JWT + Device-Token. **Permission/role required:** `RbacService::can($user, 'attendance', 'approve')` — checked in code (no route-level `permission:` middleware here, gate is inline).

**Query parameters (filters):** same as the employee endpoint (`status`, `request_type`, `search`, `start_date`, `end_date`) plus `user_id` to filter to one reportee. `per_page` default 15.

**Success response — `200`:** same shape as `view-regularization` plus a `reportees` array (`id`, `name`, `employee_id`, `designation`) listing everyone reporting to the caller.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Caller lacks `attendance:approve` | `{"success": false, "message": "Unauthorized access. Only managers, admins or supervisors can approve requests."}` |
| 200 | No reportees | `{"success": true, "message": "No reportees found", "data": [], "summary": {...zeros...}, "links": {...nulls...}}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

---

### POST /api/manager/attendance/update-regularization-approval
**Purpose:** Approve or reject a reportee's attendance regularization request.
**Auth:** JWT + Device-Token. **Permission/role required:** `attendance:approve`, and the record's owner must be covered by `scopeCoversOwner()` (admin/HR: any tenant record; manager: only direct reportees).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Must exist in `attendance_regularizations`. |
| `status` | string | Yes | `approved` or `rejected`. |
| `remarks` | string | No | Passed through to the approval workflow. |

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/manager/attendance/update-regularization-approval \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" -H "Content-Type: application/json" \
  -d '{"id":55,"status":"approved","remarks":"Confirmed with security logs"}'
```

**Success response — `200`:** `{"success": true, "message": "Attendance Regularizations status updated successfully."}` (or, under a multi-level approval chain: `"Recorded. Awaiting the next approval level."`)

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Caller lacks approve permission | `{"success": false, "message": "Unauthorized access. Only managers, admins can approve requests."}` |
| 200 | Record not found (tenant-scoped) | `{"success": false, "message": "Regularization request not found."}` |
| 200 | Caller not authorized for this specific owner (manager ≠ reporting head) | `{"success": false, "message": "You are not authorized to approve/reject this request. Only the reporting head can process it."}` |
| 200 | Already decided | `{"success": false, "message": "Cannot update a {status} Attendance Regularizations. Please contact admin if needed."}` |
| 200 | Approving a future-dated request | `{"success": false, "message": "Cannot create attendance for future date."}` |
| 200 | Multi-level approval workflow rejects the transition | `{"success": false, "message": "<RuntimeException message>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Special behavior / notes:** First tries `ApprovalService::decide('regularization', ...)` (tenant's configurable multi-level workflow); if that returns `null` (tenant has no workflow configured), falls back to a legacy single-approver update. On approval, `AttendanceEntryService::applyRegularization()` writes/updates the actual attendance record.

---

## Location Status

Controller: `app/Http/Controllers/Api/Attendance/LocationTrackingController.php`

### POST /api/location/status
**Purpose:** Report the device's location-permission/service status (on/off/denied/low-accuracy) — used to alert managers when an employee turns off location tracking mid-shift.
**Auth:** JWT + Device-Token. **Permission:** none.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | Yes | One of `on,off,low_accuracy,denied`. |
| `latitude` | numeric | No | -90 to 90. |
| `longitude` | numeric | No | -180 to 180. |
| `accuracy` | numeric | No | ≥ 0. |
| `reason` | string | No | Max 255. |
| `battery_level` | integer | No | 0–100. |

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/location/status \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" -H "Content-Type: application/json" \
  -d '{"status":"off","reason":"user_disabled_gps","battery_level":54}'
```

**Success response — `200`:**
```json
{"success": true, "message": "Location status updated successfully", "data": {"status": "off", "timestamp": "2026-09-28 14:02:11"}}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 422 | Validation failure | `{"success": false, "errors": {"<field>": ["..."]}}` (this is the one endpoint in the module using the standard Laravel validation-error shape and a real 422, not 200) |
| 401 | No authenticated user resolved | `{"success": false, "message": "User not authenticated"}` |
| 500 | Unhandled | `{"success": false, "message": "Failed to update location status: <exception message>"}` |

**Special behavior / notes:** Only `status: "off"` triggers `LocationAlertService::sendLocationOffAlert()` (notifies managers); the alert-send is wrapped in its own try/catch so a notification failure never affects the 200 response. `on`/`low_accuracy`/`denied` are accepted and acknowledged but do not themselves send an alert.

---

## Travel / WFH Requests

Controller: `app/Http/Controllers/Api/Attendance/RequestController.php` (route prefix `request`)

### GET /api/request/type
**Purpose:** List active request types (e.g. Work From Home, Business Travel) for populating a picker.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{"success": true, "message": "data fetched successfully.", "data": [{"id": 1, "type_name": "Work From Home", "description": "Remote work request"}]}
```
**Error responses:** `500 {"success": false, "message": "An error occured. Please try again later."}`.

---

### GET /api/request/view
**Purpose:** List the current user's own travel/WFH requests, paginated.
**Auth:** JWT + Device-Token. **Permission:** none. **Pagination:** fixed 15/page (not client-configurable here).

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched successfully.",
  "data": {
    "requests": [
      {"id": 12, "request_type": "Work From Home", "request_type_id": 1, "start_date": "2026-10-01", "end_date": "2026-10-02", "reason": "Internet installation", "status": "PENDING", "comments": null, "applied_date": "2026-09-28"}
    ],
    "pagination": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1, "next_page_url": null, "prev_page_url": null}
  }
}
```
**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later."}`.

---

### POST /api/request/store
**Purpose:** Submit a new travel/WFH request.
**Auth:** JWT + Device-Token. **Permission:** none. **Content-Type:** `multipart/form-data` if attaching a file.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `request_type_id` | integer | Yes | Must exist in `request_types`. |
| `start_date` | date | Yes | ≥ today. |
| `end_date` | date | Yes | ≥ `start_date`. |
| `reason` | string | Yes | Max 1000. |
| `attachment` | file | No | `jpg,jpeg,png,pdf,doc,docx`, max 5120 KB. |

**File uploads:** field `attachment`; stored on the public disk at `public/uploads/requests/attachments/` (client-supplied extension is used — not content-sniffed, unlike Expense's upload path).

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/request/store \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" \
  -F "request_type_id=1" -F "start_date=2026-10-01" -F "end_date=2026-10-02" -F "reason=Internet installation"
```

**Success response — `200`:** `{"success": true, "message": "Request created successfully."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Overlapping pending/approved request for the date range | `{"success": false, "message": "You already have a pending or approved request for this date range."}` |
| 500 | Unhandled | `{"success": false, "message": "Failed to create request. Please try again."}` |

**Special behavior / notes:** A file-upload failure is caught and logged internally but does **not** fail the request — the request is created without the attachment. Writes a `RequestHistory` audit row (`CREATED`) and an `AuditLogger` entry; sends `notifyRequestSubmitted` (best-effort).

---

### POST /api/request/update/{id}
**Purpose:** Edit a still-pending travel/WFH request the caller owns.
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Path parameters:** `id` — request ID.

**Request body:** same fields as `store` (all required again, i.e. a full replace, not a partial patch).

**Success response — `200`:** `{"success": true, "message": "Request updated successfully."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Not the request's owner | `{"success": false, "message": "Unauthorized access."}` |
| 200 | Request not editable (not pending — `canBeEdited()`) | `{"success": false, "message": "Only pending requests can be updated."}` |
| 200 | Overlapping request for new date range (excluding self) | `{"success": false, "message": "You already have another pending or approved request for this date range."}` |
| 500 | Unhandled / `findOrFail` miss | `{"success": false, "message": "Failed to update request. Please try again."}` |

---

### POST /api/request/cancel/{id}
**Purpose:** Cancel (soft-cancel — sets status to `CANCELLED`, does not delete) a pending or approved request the caller owns.
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only). **Body:** none.

**Success response — `200`:** `{"success": true, "message": "Request cancelled successfully."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Not the owner | `{"success": false, "message": "Unauthorized access."}` |
| 200 | Not cancellable (`canBeCancelled()` false — e.g. already cancelled/rejected or in the past) | `{"success": false, "message": "This request cannot be cancelled."}` |
| 500 | Unhandled / not found | `{"success": false, "message": "Failed to cancel request. Please try again."}` |

**Special behavior / notes:** If the request was `APPROVED` before cancellation, `notifyRequestCancelled($existingRequest, wasApproved: true)` is sent, presumably to alert the approver that approved leave/travel time was pulled back.

---

### GET /api/manager/request/view
**Purpose:** List travel/WFH requests visible to the caller under RBAC scope (own team, or all-tenant for admin/HR).
**Auth:** JWT + Device-Token. **Permission/role required:** route middleware `permission:requests,view`, plus an inline `RbacService::scopeFor($user,'requests','view')` check that must resolve to a scope wider than `own` (`team` or tenant-wide).

**Query parameters (filters):**
| Field | Notes |
|---|---|
| `search` | matches requester name/email/employee_id. |
| `status` | exact match. |
| `request_type_id` | exact match. |
| `from_date` / `to_date` | both required together — range on `applied_date`. |

**Pagination:** fixed 15/page.

**Success response — `200`:**
```json
{
  "success": true, "message": "Requests fetched successfully.",
  "data": {
    "requests": [
      {"id": 12, "request_type_id": 1, "request_type": "Work From Home", "name": "Asha Rao", "employee_id": "EMP-101", "email": "asha@example.com", "start_date": "2026-10-01", "end_date": "2026-10-02", "duration_days": 2, "reason": "Internet installation", "status": "PENDING", "comments": null, "applied_date": "2026-09-28"}
    ],
    "pagination": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1, "next_page_url": null, "prev_page_url": null}
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | `EnsurePermission` blocks (`requests:view` missing) | JSON abort — `{"message": "You do not have permission for this action."}` (standard `abort(403, ...)` shape) |
| 403 | Inline scope check fails (scope is `null` or `own`) | `{"success": false, "message": "You do not have permission to view these requests."}` |
| 200 | Team scope, but no reportees | `{"success": true, "message": "No team members found.", "data": {"requests": [], "pagination": {...zeros...}}}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

---

### POST /api/manager/request/update-status
**Purpose:** Approve or reject a travel/WFH request.
**Auth:** JWT + Device-Token. **Permission/role required:** route middleware `permission:requests,approve`, plus `scopeCoversOwner()` per-record check (manager limited to own reportees; admin/HR tenant-wide).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Must exist in `requests`. |
| `status` | string | Yes | `APPROVED` or `REJECTED`. |
| `comments` | string | Conditional | Required if `status = REJECTED`, else optional, max 500. |
| `remarks` | string | No | Max 250; takes precedence over `comments` for the stored value if both are given. |

**Success response — `200`:** `{"success": true, "message": "Request APPROVED successfully."}` (or `REJECTED`).

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | `EnsurePermission` blocks | abort(403) JSON shape |
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Caller not authorized for this owner | `{"success": false, "message": "You do not have permission to view these requests."}` |
| 200 | Request not pending | `{"success": false, "message": "Only pending requests can be approved or reject."}` |
| 200 | Missing required rejection comment | `{"success": false, "message": "<first validator error>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occured. Please try again later."}` |

---

### GET /api/request/detail/{id}
**Purpose:** Full detail view of one request — employee profile, request fields, attachments, and full status-change history.
**Auth:** JWT + Device-Token. **Permission/role required:** `scopeCoversOwner($user,'requests','view', ownerId)` inline (own request, or manager/admin scope covering the owner).

**Success response — `200`:**
```json
{
  "success": true,
  "message": "Request details fetched successfully.",
  "data": {
    "employee": {"id": 101, "name": "Asha Rao", "email": "asha@example.com", "employee_id": "EMP-101", "contact": "+91...", "profile_image": null},
    "request_details": {"id": 12, "request_type_id": 1, "request_type": "Work From Home", "start_date": "2026-10-01", "end_date": "2026-10-02", "duration_days": 2, "reason": "Internet installation", "status": "PENDING", "comments": null, "applied_date": "2026-09-28", "created_at": "2026-09-28 20:00:00", "updated_at": "2026-09-28 20:00:00"},
    "attachments": [{"file_url": "https://vpshrms.shurttech.com/uploads/requests/attachments/xyz.pdf", "file_type": "application/pdf"}],
    "history": [{"id": 1, "action": "CREATED", "comments": null, "action_by": {"id": 101, "name": "Asha Rao", "employee_id": "EMP-101"}, "old_values": null, "new_values": {"...":"..."}, "created_at": "2026-09-28 20:00:00"}]
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Not found | `{"success": false, "message": "Request not found."}` |
| 200 | Not authorized | `{"success": false, "message": "You do not have permission to view this request."}` |
| 500 | Unhandled (message includes raw exception text — a bug: leaks `$e->getMessage()` into the client response) | `{"success": false, "message": "An error occurred while fetching request details. Please try again later.<exception message>"}` |

---

## Overtime

> **Per-employee overtime rules (2026-10-02).** Admin/HR can give one employee custom overtime rules (Employee 360 → Policies). On `POST /api/overtime/requests/store` and `POST /api/overtime/requests/update/{id}` the employee's own daily cap / approval / auto-approve values replace the company's, and two extra refusals are possible (HTTP 200, `success: false`): `"This employee is not eligible for overtime."` and `"Overtime cannot exceed {cap} hours in a month for this employee ({booked} already booked)."`. Response shapes are unchanged.

> **Request limits (2026-10-03).** `POST /api/user/attendance/regularization` and `POST /api/request/store` (+ request update) can also refuse with HTTP 200, `success: false` when a company or per-employee limit is hit: `"Regularization can only be requested for the last N day(s)."`, `"At most N regularization request(s) can be raised for {Month YYYY} — that many are already pending or approved."`, `"Work from home must be requested at least N day(s) in advance."`, `"Work from home is limited to N day(s) a month — this would make M day(s) in {Month YYYY}."`. Travel requests are not limited. Response shapes are unchanged.

Controller: `app/Http/Controllers/Api/Attendance/OvertimeController.php` (route prefix `overtime`)

### GET /api/overtime/requests
**Purpose:** List the current user's own overtime requests, paginated.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters (filters):** `status`, `from_date`/`to_date` (range on `date`). **Pagination:** fixed 15/page.

**Success response — `200`:**
```json
{
  "success": true, "message": "Overtime requests retrieved successfully",
  "data": {
    "requests": [
      {"id": 9, "date": "2026-09-20", "date_formatted": "20 Sep 2026", "overtime_hours": "2.5", "approved_hours": null, "reason": "Month-end close", "status": "pending", "status_label": "Pending", "rejection_reason": null, "created_at": "2026-09-20T18:05:00.000000Z", "created_at_formatted": "20 Sep 2026 06:05 PM"}
    ],
    "pagination": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "next_page_url": null, "prev_page_url": null}
  }
}
```

**Error responses:** `500 {"success": false, "message": "An error occured.Please try again later."}`.

---

### GET /api/overtime/requests/detail/{id}
**Purpose:** Full detail of a single overtime request (own or, if authorized, a reportee's).
**Auth:** JWT + Device-Token. **Permission/role required:** `scopeCoversOwner($user,'overtime','view', ownerId)`.

**Success response — `200`:**
```json
{
  "success": true, "message": "Overtime request retrieved successfully",
  "data": {
    "id": 9, "user_id": 101, "user_name": "Asha Rao", "user_email": "asha@example.com", "employee_id": "EMP-101",
    "department": 3, "designation": 7, "date": "2026-09-20", "date_formatted": "20 Sep 2026",
    "overtime_hours": "2.5", "approved_hours": null, "reason": "Month-end close", "status": "pending",
    "status_label": "Pending", "rejection_reason": null, "approver_name": null,
    "created_at": "2026-09-20T18:05:00.000000Z", "created_at_formatted": "20 Sep 2026 06:05 PM",
    "approved_at": null, "approved_at_formatted": null
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Not found (tenant-scoped) | `{"success": false, "message": "Request not found"}` |
| 200 | Not authorized | `{"success": false, "message": "Unauthorized access to this request"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occured.Please try again later."}` |

---

### POST /api/overtime/requests/store
**Purpose:** Submit an overtime request for a date.
**Auth:** JWT + Device-Token. **Permission:** none.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | date | Yes | |
| `overtime_hours` | numeric | Yes | 0.5–24. |
| `reason` | string | Yes | 3–500 chars. |

**Success response — `200`:** `{"success": true, "message": "Overtime request submitted successfully"}` (or `"Overtime request auto-approved successfully"` if tenant settings auto-approve).

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Duplicate request for the date | `{"success": false, "message": "You have already submitted an overtime request for this date"}` |
| 200 | Exceeds tenant's `max_hours_per_day` setting | `{"success": false, "message": "Overtime hours cannot exceed {N} hours per day"}` |
| 500 | Unhandled — **note: response body says `"success": true` on the 500 path (bug in the controller)** | `{"success": true, "message": "An error occured.Please try again later."}` |

**Special behavior / notes:** Status is auto-set to `approved` if the tenant's `OvertimeSetting` has `require_approval = false`, or if `overtime_hours <= auto_approve_limit`; otherwise `pending`. Settings are looked up per-tenant, falling back to a global (`tenant_id IS NULL`) default row.

---

### POST /api/overtime/requests/update/{id}
**Purpose:** Edit a still-pending overtime request the caller owns.
**Auth:** JWT + Device-Token. **Permission:** none (ownership + pending-status check).

**Request body:** same as `store`.

**Success response — `200`:** `{"success": true, "message": "Overtime request updated successfully"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Not found (own + tenant scoped) | `{"success": false, "message": "Overtime request not found"}` |
| 200 | Not pending | `{"success": false, "message": "Only pending requests can be updated"}` |
| 200 | Another request already exists for the new date | `{"success": false, "message": "You already have an overtime request for this date"}` |
| 200 | Exceeds `max_hours_per_day` | `{"success": false, "message": "Overtime hours cannot exceed {N} hours per day"}` |
| 500 | Unhandled (`success: true` bug, same as store) | `{"success": true, "message": "An error occured.Please try again later."}` |

---

### GET /api/overtime/requests/delete/{id}
**Purpose:** Cancel (hard-delete) a still-pending overtime request the caller owns. **Note:** registered as `GET`, not `DELETE` — mutates state on a GET request.
**Auth:** JWT + Device-Token. **Permission:** none (ownership + pending-status check).

**Success response — `200`:** `{"success": true, "message": "Overtime request cancelled successfully"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Not found | `{"success": false, "message": "Overtime request not found"}` |
| 200 | Not pending | `{"success": false, "message": "Only pending requests can be cancelled"}` |
| 500 | Unhandled (`success: true` bug) | `{"success": true, "message": "An error occured.Please try again later."}` |

---

### GET /api/overtime/pending-approvals
**Purpose:** Admin/manager queue of tenant-wide pending overtime requests.
**Auth:** JWT + Device-Token. **Permission/role required:** inline check `in_array(auth()->user()->role, ['admin','manager'])` — the plain string `role` column, **not** `RbacService`/`permission:` middleware.

**Query parameters (filters):** `from_date`, `to_date`, `user_id`. **Pagination:** `per_page` (default 20).

**Success response — `200`:**
```json
{
  "success": true, "message": "Pending approvals retrieved successfully",
  "data": {
    "requests": [
      {"id": 9, "user": {"id": 101, "name": "Asha Rao", "email": "asha@example.com", "employee_id": "EMP-101"}, "date": "2026-09-20", "date_formatted": "20 Sep 2026", "overtime_hours": "2.5", "reason": "Month-end close", "status": "pending", "created_at": "...", "created_at_formatted": "20 Sep 2026 06:05 PM"}
    ],
    "pagination": {"current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "next_page_url": null, "prev_page_url": null}
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not admin/manager | `{"success": false, "message": "Unauthorized access. Only admin and managers can view pending approvals."}` |
| 500 | Unhandled | `{"success": false, "message": "Failed to retrieve pending approvals: <exception message>"}` (leaks exception text) |

---

### POST /api/overtime/approve/{id}
**Purpose:** Approve a pending overtime request, optionally overriding the approved hour count.
**Auth:** JWT + Device-Token. **Permission/role required:** plain `role` column, `admin`/`manager` only (same inline check as pending-approvals).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `approved_hours` | numeric | No | 0–24; defaults to the requested `overtime_hours` if omitted. |
| `comments` | string | No | Max 500. |

**Success response — `200`:** `{"success": true, "message": "Overtime request approved successfully"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not admin/manager | `{"success": false, "message": "Unauthorized to approve requests"}` |
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Not found (tenant-scoped) | `{"success": false, "message": "Request not found"}` |
| 200 | Already decided | `{"success": false, "message": "Request is already {status}"}` |
| **200 (malformed)** | `approved_hours` exceeds tenant's `max_hours_per_day` | Bare `response()->json(200)` — **this sends the integer `200` as the JSON body with HTTP status `200`, not an error object; a known bug, document as-is.** |
| 500 | Unhandled | `{"success": false, "message": "An error occured.Please try again later."}` |

---

### POST /api/overtime/reject/{id}
**Purpose:** Reject a pending overtime request.
**Auth:** JWT + Device-Token. **Permission/role required:** plain `role` column, `admin`/`manager` only.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `rejection_reason` | string | Yes | 3–500 chars. |

**Success response — `200`:** `{"success": true, "message": "Overtime request rejected"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Not admin/manager (note: 200 here, unlike `approve`'s 403) | `{"success": false, "message": "Unauthorized to reject requests"}` |
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Not found | `{"success": false, "message": "Request not found"}` |
| 200 | Already decided | `{"success": false, "message": "Request is already {status}"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occured.Please try again later."}` |
