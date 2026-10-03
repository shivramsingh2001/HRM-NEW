# Performance, Shift & Payroll API

Covers the employee's own KPI/performance dashboard, shift plan calendar, and payslips — part of the mobile/legacy JSON API (`routes/api.php`). Base URL: `https://vpshrms.shurttech.com`.

**Auth for every endpoint in this module:** JWT bearer token + matching `Device-Token` header. Tenant resolved from the JWT user. Every endpoint here is **read-only, self-service** — there are no team/manager performance, shift, or payroll endpoints on the mobile API (those stay web-only).

No fixed response envelope — shapes are per-controller, taken from the actual code.

---

## Performance

Controller: `app/Http/Controllers/Api/Performance/PerformanceController.php` — always scoped to the authenticated employee's own data.

### GET /api/user/performance/summary
**Purpose:** One month's overall KPI score, grade, and component breakdown.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `month` | string `YYYY-MM` | No | Defaults to last month. Invalid format silently falls back to the default. |

**Success response — `200`:**
```json
{
  "status": true,
  "data": {
    "month": "2026-08", "overall_score": 87.5, "grade": "A", "attendance_score": 90, "task_completion_score": 85,
    "task_ontime_score": 80, "project_participation_score": 88, "regularization_score": 95,
    "manager_rating_score": 90, "manager_rating_raw": 4.5, "manager_rating_included": true,
    "present_days": 21, "absent_days": 1, "half_days": 0, "late_days": 2, "early_departure_days": 0,
    "assigned_tasks": 15, "completed_tasks": 13, "overdue_tasks": 1, "regularization_count": 1,
    "days_calculated": 22, "days_expected": 22
  }
}
```

**Error responses:** `500 {"status": false, "message": "An error occurred. Please try again later."}`.

**Special behavior / notes:** If no `EmployeeKpiScore` row exists yet for the requested month, it is computed on demand via `PerformanceRollupService::rollupMonth()` under a 10-second cache lock (blocking up to 5 seconds) — this means the first request for a given user+month can be noticeably slower than subsequent ones.

---

### GET /api/user/performance/history
**Purpose:** Last N months of monthly KPI scores (trend chart data).
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `limit` | integer | No | Default 6, clamped to 1–24. |

**Success response — `200`:** `{"status": true, "data": [<same shape as one summary() item, newest first>]}`
**Error responses:** `500 {"status": false, "message": "An error occurred. Please try again later."}`.

---

### GET /api/user/performance/daily
**Purpose:** Daily performance score rows for one month (daily trend chart), calculated days only.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `month` | string `YYYY-MM` | No | Defaults to last month; invalid input falls back to default. Range is capped to today if the month is the current month. |

**Success response — `200`:**
```json
{"status": true, "data": [{"performance_date": "2026-08-01", "overall_daily_score": 88, "attendance_score": 90, "task_completion_score": 85, "task_ontime_score": 80, "project_participation_score": 88, "regularization_score": 100, "day_type": "working", "attendance_status": "present"}]}
```
Only rows with `calculation_status = 'calculated'` are returned.

**Error responses:** `500 {"status": false, "message": "An error occurred. Please try again later."}`.

---

### GET /api/user/performance/weekly
**Purpose:** On-demand weekly score buckets for one month (7-day chunks from the 1st) — computed live, never persisted.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `month` | string `YYYY-MM` | No | Defaults to last month. |

**Success response — `200`:** `{"status": true, "data": [{"...fields from PerformanceRollupService::weekly()...", "label": "Week 1"}, {"...": "...", "label": "Week 2"}]}`
**Error responses:** `500 {"status": false, "message": "An error occurred. Please try again later."}`.

**Special behavior / notes:** Every request recomputes all weekly buckets live (`PerformanceRollupService::weekly()` per 7-day window) — this is the most compute-heavy of the performance endpoints since nothing is cached.

---

### GET /api/user/performance/daily-detail
**Purpose:** Full score breakdown for a single day.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | string `YYYY-MM-DD` | No | Defaults to yesterday; invalid format silently falls back to the default. |

**Success response — `200`:** `{"status": true, "data": {"...full EmployeeDailyPerformance row as an array, all columns..."}}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | No row calculated for that date yet | `{"status": false, "message": "No data calculated for this date yet."}` |
| 500 | Unhandled | `{"status": false, "message": "An error occurred. Please try again later."}` |

---

### GET /api/user/performance/review
**Purpose:** The manager's performance review for one month, if submitted or already acknowledged.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `month` | string `YYYY-MM` | No | Defaults to last month. |

**Success response — `200`:**
```json
{
  "status": true,
  "data": {
    "id": 88, "review_month": "2026-08-01", "reviewer_name": "Vikram Shah", "overall_rating": 4.5,
    "strengths": "...", "areas_for_improvement": "...", "achievements": "...", "goals_next_month": "...",
    "additional_feedback": "...", "status": "submitted", "submitted_at": "2026-09-05 10:00:00", "employee_acknowledged_at": null
  }
}
```
If no submitted/acknowledged review exists for the month: `{"status": true, "data": null}`.

**Error responses:** `500 {"status": false, "message": "An error occurred. Please try again later."}`.

---

### POST /api/user/performance/review/{id}/acknowledge
**Purpose:** Employee acknowledges their own submitted review.
**Auth:** JWT + Device-Token. **Permission:** none (self only — checked inline). **Body:** none.

**Success response — `200`:** `{"status": true, "message": "Review acknowledged successfully."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not the review's owner | `{"status": false, "message": "Only the employee can acknowledge the review."}` |
| 400 | Review not in `submitted` status | `{"status": false, "message": "Only submitted reviews can be acknowledged."}` |
| 500 | Unhandled / not found (`findOrFail` throws into the generic catch) | `{"status": false, "message": "An error occurred. Please try again later."}` |

---

## Shift

Controller: `app/Http/Controllers/Api/Shift/ShiftController.php`

### GET /api/user/shift/plan
**Purpose:** Current user's shift assignment / week-off calendar over a date range, with summary counts.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `start_date` | date | No | Defaults based on joining date logic (see below). If earlier than the user's joining date, snapped to the joining month's start. |
| `end_date` | date | No | Defaults to end of the current month. |

**Sample request:**
```bash
curl "https://vpshrms.shurttech.com/api/user/shift/plan?start_date=2026-09-01&end_date=2026-09-30" \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>"
```

**Success response — `200`:**
```json
{
  "success": true, "message": "Shift plan fetched successfully",
  "data": {
    "shifts": [
      {"date": "2026-09-01", "shift_id": 3, "shift_name": "General Shift", "start_time": "09:30 AM", "end_time": "06:30 PM", "status": "completed", "type": "Shift", "color_code": "#3b82f6"},
      {"date": "2026-09-06", "shift_id": null, "shift_name": null, "start_time": null, "end_time": null, "status": "Week Off", "type": "Week Off", "color_code": "#dc3545"}
    ],
    "summary": {"total": 30, "shifts": 26, "week_offs": 4, "upcoming": 0, "ongoing": 0, "completed": 26, "missed": 0, "cancelled": 0}
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 401 | No authenticated user | `{"success": false, "message": "User not authenticated"}` |
| 400 | `end_date` before `start_date` | `{"success": false, "message": "End date cannot be before start date"}` |
| 400 | Range exceeds 6 months | `{"success": false, "message": "Date range cannot exceed 6 months"}` |
| 500 | Unhandled | `{"success": false, "message": "Failed to fetch shift plan", "error": "<exception message, only when app.debug is true>"}` |

**Special behavior / notes:** Multi-shift (added 2026-10-02): each date item is the day's main shift (same keys as before) and carries `additional_shifts` — an array of the day's 2nd+ shifts in the same item shape (empty when there is only one). If the tenant has custom shifts **disabled** (`tenant->custom_shifts_enabled = false`), every working day is filled with the tenant's single default/fixed shift instead of reading per-user `user_shifts` assignments (day-based week-off patterns are still honored). Default start date has joining-date-aware logic: joined this month after the 15th → starts next month; otherwise starts this month; joined in a future month → starts that month.

---

## Payroll (Payslips)

Controller: `app/Http/Controllers/Api/Payroll/PayrollController.php` — always scoped to the authenticated employee's own payslips (`payment_status` must be `processed` or `paid` to be viewable/downloadable; `pending` payrolls are invisible to these endpoints except the list).

### GET /api/payslips
**Purpose:** List the current user's available (processed/paid) payslips.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 501, "month": "August 2026", "month_code": "2026-08", "net_salary": "45230.00", "status": "paid",
      "is_available": true, "download_url": "https://vpshrms.shurttech.com/payslip/501/download",
      "view_url": "https://vpshrms.shurttech.com/payslip/501/view", "base64_url": "https://vpshrms.shurttech.com/payslip/501/base64",
      "generated_on": "02 Sep 2026", "payment_date": "05 Sep 2026"
    }
  ]
}
```

**Error responses:** `500 {"success": false, "message": "Failed to fetch payslips: <exception message>"}` (leaks exception text).

**Special behavior / notes:** The controller also computes a `pendingPayrolls` list (payrolls still `pending`, with an `expected_availability` date) but this is **not included in the returned JSON** — only `$payslips` (processed/paid) is returned; the pending computation appears to be dead code in the current implementation.

---

### GET /api/payslip/{id}/check
**Purpose:** Check whether a specific payslip is available yet, and get its URLs if so.
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Success response — `200`:**
```json
{
  "success": true,
  "data": {
    "id": 501, "month": "August 2026", "month_code": "2026-08", "status": "paid", "is_available": true,
    "can_download": true, "can_view": true, "message": "Payslip ready", "expected_date": null,
    "urls": {"view": "https://vpshrms.shurttech.com/payslip/501/view", "download": "https://vpshrms.shurttech.com/payslip/501/download", "base64": "https://vpshrms.shurttech.com/payslip/501/base64"}
  }
}
```
If not yet available: `is_available/can_download/can_view: false`, `message: "Payslip not yet available"`, `expected_date` set (only when `status = "pending"`) to end-of-month + 5 days, `urls: null`.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 404 | Not found / not owned by caller | `{"success": false, "message": "Payslip not found"}` |
| 500 | Unhandled | `{"success": false, "message": "Failed to check payslip: <exception message>"}` |

---

### GET /api/payslip/{id}/summary
**Purpose:** Structured earnings/deductions/totals breakdown for one payslip (for an in-app summary screen, no PDF).
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Success response — `200`:**
```json
{
  "success": true,
  "data": {
    "id": 501, "month": "August 2026", "month_code": "2026-08",
    "employee": {"name": "Asha Rao", "employee_id": "EMP-101", "designation": "N/A", "department": "N/A"},
    "attendance": {"working_days": 22, "present_days": 21, "absent_days": 1, "paid_leaves": 0, "overtime_hours": 3.5},
    "earnings": [{"name": "Basic Salary", "amount": 25000}, {"name": "HRA", "amount": 10000}],
    "deductions": [{"name": "Provident Fund", "amount": 1800}],
    "totals": {"gross_earnings": 45230, "total_deductions": 1800, "net_payable": 43430},
    "status": "paid", "payment_date": "05 Sep 2026",
    "urls": {"view": "https://vpshrms.shurttech.com/payslip/501/view", "download": "https://vpshrms.shurttech.com/payslip/501/download", "base64": "https://vpshrms.shurttech.com/payslip/501/base64"}
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Payslip not yet processed/paid | `{"success": false, "message": "Payslip not yet available"}` |
| 500 | Not found (`firstOrFail` throws into the catch) or unhandled | `{"success": false, "message": "Failed to fetch payslip summary: <exception message>"}` |

**Special behavior / notes:** `employee.designation`/`employee.department` reference `jobDetails->designation_name`/`department_name` accessors — if these aren't populated the response shows the literal string `"N/A"`. Earnings/deductions with a zero amount are filtered out of the arrays entirely.

---

### GET /api/payslip/{id}/view
**Purpose:** Render the payslip PDF — returns a raw PDF stream to a browser, or a base64-JSON payload for an API/mobile client.
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Content negotiation:** if the request has `Accept: application/json` or a `?api=1` query param, returns JSON with base64 PDF; otherwise streams `application/pdf` directly (`Content-Disposition: inline`).

**Success response — `200` (JSON path):**
```json
{
  "success": true,
  "data": {
    "pdf_base64": "<base64 string>", "filename": "payslip_EMP-101_2026-08.pdf", "content_type": "application/pdf",
    "size": 48213, "month": "August 2026", "employee_name": "Asha Rao", "net_payable": "43430.00"
  }
}
```
**Success response — `200` (browser path):** raw `application/pdf` bytes, inline.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not yet processed/paid | `{"success": false, "message": "Payslip not yet available"}` |
| 500 (JSON) / redirect back with flash error (browser) | Not found or unhandled | JSON: `{"success": false, "message": "Failed to view payslip: <exception message>"}`; browser: redirect with `error` flash message |

---

### GET /api/payslip/{id}/download
**Purpose:** Download the payslip PDF as an attachment.
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Success response — `200`:** raw `application/pdf` bytes with `Content-Disposition: attachment; filename="payslip_{employee_id}_{month_code}.pdf"`.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not yet processed/paid | `{"success": false, "message": "Payslip not yet available"}` |
| 500 | Not found or unhandled | `{"success": false, "message": "Failed to download payslip: <exception message>"}` |

---

### GET /api/payslip/{id}/base64
**Purpose:** Get the payslip PDF as a base64 string (explicit mobile-app-oriented endpoint — same content as `/view`'s JSON path but always JSON, no content negotiation).
**Auth:** JWT + Device-Token. **Permission:** none (ownership check only).

**Success response — `200`:**
```json
{
  "success": true,
  "data": {
    "id": 501, "pdf_base64": "<base64 string>", "filename": "payslip_EMP-101_2026-08.pdf", "content_type": "application/pdf",
    "size": 48213, "month": "August 2026", "month_code": "2026-08", "employee_name": "Asha Rao",
    "employee_id": "EMP-101", "net_payable": "43430.00", "status": "paid"
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Not yet processed/paid | `{"success": false, "message": "Payslip not yet available"}` |
| 500 | Not found or unhandled | `{"success": false, "message": "Failed to generate payslip: <exception message>"}` |
