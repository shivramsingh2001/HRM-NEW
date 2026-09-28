# Project & Task API

Covers project listings and task assignment/tracking (individual, self-assigned, and group tasks) — part of the mobile/legacy JSON API (`routes/api.php`). Base URL: `https://vpshrms.shurttech.com`.

**Auth for every endpoint in this module:** JWT bearer token + matching `Device-Token` header. Tenant resolved from the JWT user.

No fixed response envelope — shapes are per-controller, taken from the actual code.

---

## Project

Controller: `app/Http/Controllers/Api/Project/ProjectController.php`

### GET /api/user/get-project
**Purpose:** List projects visible to the current user — all tenant projects if the caller's `role` is `manager`, otherwise only projects they're assigned to.
**Auth:** JWT + Device-Token. **Permission:** none (role-based branch, not RBAC-driven).

**Success response — `200`:**
```json
{"success": true, "message": "Data fetched Successfully", "data": [{"id": 5, "project_code": "PRJ-01", "name": "Client Onboarding", "start_date": "2026-08-01", "deadline_date": "2026-12-31", "description": "...", "status": "active", "project_head": "Vikram Shah", "member_count": 4}]}
```

**Error responses:** `500 {"success": false, "message": "An Error occured. Please try again later."}`.

**Special behavior / notes:** The `role === 'manager'` check is the plain string column, not RBAC — an `admin` or `hr` user hits the "else" branch and only sees projects they're personally assigned to via `project_assigns`, not every tenant project.

---

### GET /api/manager/get-project
**Purpose:** List projects where the current user is either an active assignee or the project head — de-duplicated.
**Auth:** JWT + Device-Token. **Permission:** none (despite the `manager/` URL prefix, no role/permission gate — any authenticated user can call this).

**Success response — `200`:** same item shape as `user/get-project`, but `member_count` only counts assignments with `status = 1`.

**Error responses:** `500 {"success": false, "message": "An Error occured. Please try again later.<exception message>"}` (leaks exception text).

---

### GET /api/project-show/{id}
**Purpose:** Full detail of a single project plus its member roster.
**Auth:** JWT + Device-Token. **Permission:** none — **no ownership/membership check; any authenticated user can view any project by ID, including cross-tenant if the ID exists (no explicit `tenant_id` filter in the query).**

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched Successfully",
  "data": {
    "project": {"id": 5, "project_code": "PRJ-01", "name": "Client Onboarding", "start_date": "2026-08-01", "deadline_date": "2026-12-31", "description": "...", "status": "active", "project_head": "Vikram Shah", "member_count": 4},
    "members": [{"id": 101, "employee_id": "EMP-101", "name": "Asha Rao", "email": "asha@example.com", "is_head": 0, "profile_image": "https://vpshrms.shurttech.com/uploads/users/asha.jpg", "designation": "Software Engineer", "department": "Engineering", "assigned_at": "2026-08-01T09:00:00.000000Z", "status": "active"}]
  }
}
```

**Error responses:** `500 {"success": false, "message": "An Error occured. Please try again later."}` (also returned, rather than a 404, when the ID doesn't exist — `project` comes back `null`/empty and `members` empty).

---

## Task

Controller: `app/Http/Controllers/Api/Task/TaskController.php`

### POST /api/manager/task-assign
**Purpose:** Create a task — individually assigned, self-assigned (routes to the creator's reporting head as the "assigned by"), or a group task with a completion rule.
**Auth:** JWT + Device-Token. **Permission:** none — **despite the `manager/` URL prefix, there is no role/permission check in the code (commented out); any authenticated user can create and assign tasks to anyone.**
**Content-Type:** `multipart/form-data` if attaching a file/voice note.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | string | Yes | Max 255. |
| `description` | string | Yes | |
| `task_date` | date | Yes | ≥ today. |
| `deadline_date` | date | No | ≥ today. |
| `priority` | string | Yes | `low`, `medium`, `high`, `critical`. |
| `self_assigned` | integer | Yes | `0` = assign to someone else, `1` = self-assigned, `2` = group task. |
| `assigned_to` | integer | Conditional | Required (and must exist in `users`) when `self_assigned = 0`. |
| `project_id` | integer | No | Must exist in `projects`. |
| `file` | file | No | `jpg,jpeg,png,pdf,doc,docx,xls,xlsx`, max 5120 KB. |
| `voice_file` | file | No | Any mime, max 10240 KB. |
| `group_members` | array of integers | Conditional | Required when `self_assigned = 2`; min 2, each must exist in `users` and be distinct. |
| `group_completion_rule` | string | Conditional | Required when `self_assigned = 2`; one of `all_must_complete,any_one,percentage,lead_decides`. |
| `completion_threshold` | integer | Conditional | Required if `group_completion_rule = percentage`; 1–100. |
| `group_lead_id` | integer | Conditional | Required if `group_completion_rule = lead_decides`; must exist in `users`. |

**File uploads:** `file` → `public/uploads/task/document/`; `voice_file` → `public/uploads/task/voice/`.

**Success response — `200`:** `{"success": true, "message": "Task created successfully!"}` (or, for a group task: `"Group task created and assigned to {N} members."`)

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later. "}` |

**Special behavior / notes:** `task_code` is generated inside a locked DB transaction (`TASK-00001`, `TASK-00002`, ...) to avoid duplicates under concurrent submits, wrapping back to `TASK-00001` after `99999`. For `self_assigned = 1` (self-assigned), the task's `assigned_by` is set to the creator's `reporting_head` (from `user_job_details`) rather than the creator themselves — i.e. self-assigned tasks are still logically "assigned by" the employee's manager for approval-flow purposes. One push notification is sent per group member; a per-member notification failure is logged and does not fail the request or other members' notifications.

---

### GET /api/manager/task/view-assign-by-me
**Purpose:** Paginated list of tasks the current user has assigned to others, with filters and per-task member roster.
**Auth:** JWT + Device-Token. **Permission:** none (despite the `manager/` prefix — any authenticated user who has assigned at least one task can call this; the commented-out role check was removed).

**Query parameters (filters):**
| Field | Notes |
|---|---|
| `assigned_to` | filter to one assignee, or `all`. |
| `project_id` | filter to one project, or `all`. |
| `date_from` / `date_to` | range on `deadline_date`. |
| `status` | exact match, or `all`. |
| `priority` | exact match, or `all`. |
| `sort_by` | `priority_asc`, `deadline_asc`, `deadline_desc`, `created_desc`, `created_asc`; default sorts by priority descending (`critical` first). |
| `per_page` | default 15. |

**Success response — `200`:**
```json
{
  "success": true, "message": "Tasks assigned by you fetched successfully",
  "data": [{"id": 88, "task_code": "TASK-00088", "title": "Prepare Q3 report", "priority": "high", "status": "pending", "task_date": "2026-09-25", "deadline_date": "2026-10-01", "project_name": "Client Onboarding", "project_code": "PRJ-01", "assigned_to": "Asha Rao", "assigned_by": "Vikram Shah", "task_mode": "individual"}],
  "pagination": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1, "first_page_url": "...", "last_page_url": "...", "prev_page_url": null, "next_page_url": null}
}
```

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later.", }`.

---

### GET /api/task/view-assign-to-me
**Purpose:** Paginated list of tasks assigned to the current user, sorted by priority then nearest deadline.
**Auth:** JWT + Device-Token. **Permission:** none.

**Query parameters:** `per_page` (default 15).

**Success response — `200`:**
```json
{
  "success": true, "message": "Tasks assigned to you fetched successfully",
  "data": [{"id": 88, "task_code": "TASK-00088", "task_mode": "individual", "title": "Prepare Q3 report", "priority": "high", "status": "pending", "task_date": "2026-09-25", "deadline_date": "2026-10-01", "project_name": "Client Onboarding", "project_code": "PRJ-01", "assigned_by": "Vikram Shah", "assigned_to": "Asha Rao"}],
  "pagination": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1, "first_page_url": "...", "last_page_url": "...", "prev_page_url": null, "next_page_url": null}
}
```

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later."}`.

---

### POST /api/task/update-status
**Purpose:** Update the caller's own task-assignment status, optionally extending the deadline (and thereby requesting approval if marking as completed).
**Auth:** JWT + Device-Token. **Permission:** none — checked inline (caller must be assigned to the task).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Must exist in `tasks`. |
| `status` | string | Yes | `pending`, `in_progress`, `hold`, `completed`, `cancelled`. |
| `remarks` | string | Yes | Max 1000. |
| `extend_deadline` | boolean | Yes | |
| `new_deadline` | date | Conditional | Required if `extend_deadline = true`. |
| `extension_reason` | string | No | Max 500. |

**Success response — `200`:** `{"success": true, "message": "Task updated successfully"}` (with suffixes appended: `" and deadline extended to {date}"` if extended, `". Approval request has been sent to the task assigner."` if marking completed.)

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Caller not assigned to the task | `{"success": false, "message": "You are not assigned to this task. Only assigned users can update tasks."}` |
| 200 | Task already in a final state (`completed`, `approved`, `rejected`, `cancelled`) | `{"success": false, "message": "Cannot update a {status} task. Please contact admin if needed."}` |
| 200 | `extend_deadline = true` but no `new_deadline` supplied | `{"success": false, "message": "New deadline is required when extending deadline."}` |
| 500 | Unhandled — **leaks raw exception text appended to the message, plus a separate `error` field when `app.debug` is true** | `{"success": false, "message": "An error occurred. Please try again later.<exception message>", "error": "<exception message or null>"}` |

**Special behavior / notes:** Every status change writes an immutable `TaskUpdate` audit row (old/new deadline, remarks, who/when). Marking `completed` does not itself finalize the task — it stays `completed` pending the assigner's separate approval via `task-approval`.

---

### POST /api/manager/update-approval
**Purpose:** Approve or reject a completed task; rejecting spins up a brand-new task (same details, `parent_task_id` linked) reassigned to the same person.
**Auth:** JWT + Device-Token. **Permission:** none — checked inline (caller must be the task's assigner, i.e. `task_assigns.assigned_by = caller`).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Must exist in `tasks`. |
| `status` | string | Yes | `approved` or `rejected`. |
| `remarks` | string | Yes | Max 1000. |

**Success response — `200`:** `{"success": true, "message": "Task approved successfully"}` or `{"success": true, "message": "Task rejected and new task created successfully. The employee has been reassigned the task."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Caller is not the task's assigner | `{"success": false, "message": "Only the person who assigned this task can approve/reject it."}` |
| 200 | Task not in `completed` status | `{"success": false, "message": "Only completed tasks can be approved or rejected."}` |
| 500 | Unhandled — leaks exception text | `{"success": false, "message": "An error occurred. Please try again later. <exception message>"}` |

**Special behavior / notes:** On rejection, a full duplicate `Task` row is created (new `task_code`, `parent_task_id` pointing at the original, `rejection_remarks` set to the approver's remarks) and re-assigned to the same employee with a fresh `pending` status — this is how "send back for rework" is modeled, not an in-place reopen.

---

### GET /api/task/view-detail/{taskId}
**Purpose:** Full task detail — assignment info, members (individual/group), status-update history, and approval record.
**Auth:** JWT + Device-Token. **Permission:** caller must be assigned-to or assigned-by on the task, or hold an "elevated" role per `TaskPermissionService::isElevated()`.

**Success response — `200`:** A large object: core task fields (`id`, `task_code`, `title`, `description`, `priority`, `status`, `task_date`, `deadline_date`, `file`/`voice_file` as full asset URLs, `is_overdue`), `assigned_by`, `assigned_to` (string name, or `"Self Assigned"`, or `null` for group tasks), `project` (name/code/description or `null`), `approval` (legacy shape) and `approval_details` (fuller shape) if an approval exists, `updates[]` (full audit trail), plus group-task fields (`task_mode`, `self_assigned`, `group_completion_rule`, `completion_threshold`, `group_lead_id`, `original_deadline_date`, `extension_count`), `assigned_by_details`, `assigned_to_details` (individual tasks only), `members[]` (full roster with `individual_status`/`individual_remarks`/`started_at`/`completed_at` per member), `member_count`, `group_lead_details`.

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | No access (not assigned, not assigner, not elevated) | `{"success": false, "message": "You do not have access to this task."}` |
| 200 | Not found | `{"success": false, "message": "Task not found."}` |
| 500 | Unhandled — leaks exception text | `{"success": false, "message": "An error occurred. Please try again later.<exception message>"}` |
