# Expense & Loan API

Covers employee expense claims (advance/settlement/reimbursement), expense approval, payment history, and employee loans — part of the mobile/legacy JSON API (`routes/api.php`). Base URL: `https://vpshrms.shurttech.com`.

**Auth for every endpoint in this module:** JWT bearer token + matching `Device-Token` header. Tenant resolved from the JWT user.

No fixed response envelope — shapes are per-controller, taken from the actual code. **Expense is the one fully-layered module in this codebase** (`docs/architecture.md`) — validation is shared with the web panel via `StoreExpenseRequest`/`DecideExpenseRequest` FormRequest classes (one of only 4 FormRequests in the app), and persistence goes through `ExpenseService`/`ExpenseAttachmentService`/`ExpenseStatsService`, not ad hoc controller code.

---

## Expense

Controller: `app/Http/Controllers/Api/Expense/ExpenseController.php`

### GET /api/expense-type
**Purpose:** List active expense types for a picker.
**Auth:** JWT + Device-Token. **Permission:** none.
**Success response — `200`:** `{"success": true, "message": "Data fetched successfully!!!", "data": [{"id": 1, "name": "Travel", "description": null}]}`
**Error responses:** `500 {"success": false, "message": "An error occured. Please try again later."}`.

---

### GET /api/view-expense
**Purpose:** Current user's own expense claims, balance summary, and recent ledger transactions.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched successfully!!!",
  "data": {
    "expenses": [
      {
        "id": 210, "expense_number": "EXP-000210", "expense_type": "Travel", "project_name": "Client Onboarding",
        "requirement_type": "reimbursement", "date": "2026-09-20", "amount": "1250.00", "status": "pending",
        "description": "Cab fare", "possible_duplicate_of": null, "withdrawn_at": null,
        "file_url": "https://vpshrms.shurttech.com/expense/file/210?tenant=...&expires=...&signature=...",
        "receipts": [{"...": "..."}], "possible_duplicate": false
      }
    ],
    "statistics": {"total_expenses": 12, "total_amount": "15400.00", "...": "per-type / by-status breakdown from ExpenseStatsService::apiSummary()"},
    "balance": {"current_balance": "2500.00", "total_advance_taken": "5000.00", "total_settlement_done": "2000.00", "total_reimbursement_done": "500.00", "total_expenses_done": "2500.00"},
    "recent_transactions": [{"...": "up to 10 most recent ExpenseTransaction rows, with the related expense loaded"}]
  }
}
```

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later. "}`.

**Special behavior / notes:** `file_url`/`receipts` are built via `ExpenseAttachmentService` — receipts are stored on the **private** disk and served through the signed, 30-minute-expiring `GET /expense/file/{id}?tenant=...` route (outside this API's tenant/JWT middleware, deliberately — see `docs/architecture.md` § "Expense receipts & uploads"). The raw `expenses.file` DB column is hidden from the response (`makeHidden`).

---

### GET /api/view-expense-payments
**Purpose:** History of payments made to the current user, with the payment voucher each belongs to.
**Auth:** JWT + Device-Token. **Permission:** none.

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched successfully!!!",
  "data": {
    "payments": [{"id": 55, "payment_date": "2026-09-25", "amount": "1250.00", "payment_mode": "bank_transfer", "reference_number": "TXN123", "status": "posted", "voucher_number": "VCH-0099", "expense_number": "EXP-000210", "requirement_type": "reimbursement"}],
    "total_received": "1250.00",
    "payment_count": 1
  }
}
```
Limited to the most recent 200 rows. Voided payments (`status: "voided"`) are included in `payments` (for a struck-through UI) but excluded from `total_received`/`payment_count`, which only count `status: "posted"`.

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later."}`.

---

### POST /api/withdraw-expense
**Purpose:** Withdraw the current user's own claim — must still be pending, or an approved-but-unpaid advance/reimbursement.
**Auth:** JWT + Device-Token. **Permission:** none (self only).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | The expense's ID. |
| `reason` | string | Yes | 3–500 chars. |

**Success response — `200`:** `{"success": true, "message": "Your claim has been withdrawn."}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Business rule violation from `ExpenseService::withdraw()` (e.g. wrong owner, wrong state, already paid) | `{"success": false, "message": "<ExpenseException message>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

---

### POST /api/create-expense
**Purpose:** Submit an expense claim (advance, settlement, or reimbursement) with up to 5 receipts.
**Auth:** JWT + Device-Token. **Permission:** none. **Content-Type:** `multipart/form-data`.
**Validation:** shared `App\Http\Requests\Expense\StoreExpenseRequest` (also used by the web panel).

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `expense_type` | integer | Yes | Must exist in `expense_types`. |
| `requirement_type` | string | Yes | `advance`, `settlement`, or `reimbursement`. |
| `amount` | numeric | Yes | > 0, ≤ 9,999,999.99, at most 2 decimal places. |
| `date` | date | Yes | For `advance`, any date is allowed (future request); for `settlement`/`reimbursement`, must be today or earlier. |
| `project_id` | integer | No | Must exist in `projects`. |
| `description` | string | No | Max 1000. |
| `file` | file | No | Single receipt (legacy mobile field) — `jpg,jpeg,png,pdf`, max 5120 KB. |
| `files[]` | file array | No | Additional receipts (web-form style, also accepted here) — same type/size rule each, max 5 total across `file` + `files[]`. |

**File uploads:** `file` and/or `files[]`; content-sniffed extension, stored on the private `local` disk under `expense/<tenantId>/<year>/`. Allowed: `jpg,jpeg,png,pdf`, ≤5 MB each, ≤5 receipts per claim.

**Sample request:**
```bash
curl -X POST https://vpshrms.shurttech.com/api/create-expense \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: <device-token>" \
  -F "expense_type=1" -F "requirement_type=reimbursement" -F "amount=1250.00" -F "date=2026-09-20" \
  -F "description=Cab fare" -F "file=@receipt.jpg"
```

**Success response — `200`:** `{"success": true, "message": "Expense submitted successfully"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure (via `StoreExpenseRequest::failedValidation` — this FormRequest deliberately keeps the mobile API's 200-envelope convention while the web panel gets a real 422) | `{"success": false, "message": "<first validator error>"}` |
| 200 | Business-rule failure from `ExpenseService::submit()` (e.g. policy violation, duplicate flag rules, budget cap) | `{"success": false, "message": "<ExpenseException message>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later. "}` |

**Special behavior / notes:** Runs through the same `ExpenseService::submit()` used by the web panel — policy checks (`ExpensePolicyService`), budget caps (`ExpenseBudgetService`), and duplicate-claim flagging are identical on both surfaces (this used to differ — the API previously accepted any file type and negative amounts before the shared FormRequest was introduced).

---

### GET /api/view-team-expense
**Purpose:** List expense claims visible to the caller under RBAC scope, plus team balances.
**Auth:** JWT + Device-Token. **Permission/role required:** `RbacService::scopeFor($user,'expenses','view')` must be `team` or `company`.

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched successfully!",
  "data": {
    "expenses": [{"id": 210, "expense_number": "EXP-000210", "employee_id": "EMP-101", "employee_name": "Asha Rao", "employee_email": "asha@example.com", "employee_profile_image": null, "expense_type": "Travel", "date": "2026-09-20", "project_name": "Client Onboarding", "project_code": "PRJ-01", "requirement_type": "reimbursement", "amount": "1250.00", "description": "Cab fare", "status": "pending", "created_at": "...", "file_url": "https://vpshrms.shurttech.com/expense/file/210?..."}],
    "team_members": [{"id": 101, "name": "Asha Rao", "email": "asha@example.com", "employee_id": "EMP-101"}],
    "team_balances": [{"user_id": 101, "user_name": "Asha Rao", "current_balance": 2500, "total_advance_taken": 5000, "total_settlement_done": 2000}],
    "statistics": {"...": "ExpenseStatsService::apiSummary()"}
  }
}
```

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 403 | Scope not team/company | `{"success": false, "message": "Unauthorized access. Only managers can view this data."}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later. "}` |

---

### POST /api/team/expense-update
**Purpose:** Approve or reject an expense claim, optionally covering an advance shortfall with a payable reimbursement.
**Auth:** JWT + Device-Token. **Permission/role required:** `scopeCoversOwner($authUser,'expenses','approve', ownerId)` inline (no route-level `permission:` middleware).
**Validation:** shared `App\Http\Requests\Expense\DecideExpenseRequest`.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Must exist in `expenses` (required specifically on the API surface — the web route carries it in the URL instead). |
| `status` | string | Yes | `approved` or `cancelled`. |
| `remarks` | string | No | Max 500. |
| `cover_shortfall` | boolean | No | If a settlement exceeds the employee's advance balance, opts in to splitting it: advance covers what it can, the rest becomes a payable reimbursement. |

**Success response — `200`:** `{"success": true, "message": "<message from ExpenseService::decide()>"}`

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Expense not found | `{"success": false, "message": "Expense not found."}` |
| 200 | Caller not authorized for this owner | `{"success": false, "message": "You are not authorized to update this expense."}` |
| 200 | Shortfall not covered (`cover_shortfall` not set/true) | `{"success": false, "message": "<InsufficientBalanceException message>", "available": ..., "requested": ..., "shortfall": ...}` (extra fields come from `InsufficientBalanceException::toPayload()`) |
| 200 | Other business-rule failure | `{"success": false, "message": "<ExpenseException message>"}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Special behavior / notes:** Every money-changing expense action locks rows in order budgets → expenses → payments → balance and runs inside a DB transaction (see `docs/architecture.md`). Notification (`notifyExpenseApproved`/`notifyExpenseRejected`) is sent after a successful decision, failure is logged but not surfaced to the client.

---

## Loan

Controller: `app/Http/Controllers/Api/Loan/LoanController.php`

### GET /api/loan/categories
**Purpose:** List active loan categories (with their limits) for a picker.
**Auth:** JWT + Device-Token. **Permission:** none.
**Success response — `200`:** `{"success": true, "message": "Data fetched successfully!!!", "data": [{"id": 1, "name": "Personal Loan", "max_amount": "100000.00", "max_tenure_months": 24, "default_interest_rate": "10.00", "requires_approval": 1, "status": "1", "...": "full LoanCategory row"}]}`
**Error responses:** `500 {"success": false, "message": "An error occured. Please try again later."}`.

---

### GET /api/loan/view
**Purpose:** List loans visible to the caller under RBAC scope, paginated.
**Auth:** JWT + Device-Token. **Permission/role required:** `RbacService::scopeFor($user,'loans','view')` — `own` (self only), `team` (self + reportees), `company` (tenant-wide), or `null` (nothing — forces an always-false query, not a 403).

**Query parameters (filters):**
| Field | Notes |
|---|---|
| `status` | exact match (`pending`, `approved`, `active`, `closed`, `cancelled`). |
| `user_id` | only applied when scope is `team` or `company` (ignored under `own` scope). |
| `per_page` | default 15. |

**Success response — `200`:**
```json
{
  "success": true, "message": "Data Fetched Successfully.",
  "data": {
    "loans": [
      {
        "id": 12, "loan_number": "LN-000012", "amount": "50,000.00", "emi_amount": "4,500.00", "remaining_amount": "45,500.00",
        "tenure_months": 12, "interest_rate": "10%", "repayment_type": "emi", "status": "active", "status_label": "Active",
        "purpose": "Medical", "loan_date": "2026-08-01", "first_emi_date": "2026-09-07", "lumpsum_due_date": null,
        "closed_date": null, "created_at": "...", "category": "Personal Loan", "approved_by": "Vikram Shah",
        "progress": {"percentage": 10.0, "paid_amount": "4,500.00", "remaining_amount": "45,500.00"}
      }
    ],
    "pagination": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1, "next_page_url": null, "prev_page_url": null},
    "filters": {"status": null, "user_id": null, "per_page": 15}
  }
}
```

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later.", "error": "<exception message, only when app.debug is true>"}`.

**Special behavior / notes:** `progress` is only populated for `status: "active"` loans, empty array otherwise. Numeric fields (`amount`, `emi_amount`, `remaining_amount`) are formatted as comma-separated strings, not raw numbers.

---

### POST /api/loan/store
**Purpose:** Apply for a new loan (EMI or lump-sum repayment), auto-generating the repayment schedule if the category doesn't require approval.
**Auth:** JWT + Device-Token. **Permission:** none. **Content-Type:** `multipart/form-data` if attaching a document.

**Request body:**
| Field | Type | Required | Notes |
|---|---|---|---|
| `loan_type_id` | integer | Yes | Must exist in `loan_categories`. |
| `repayment_type` | string | Yes | `emi` or `lumpsum`. |
| `amount` | numeric | Yes | ≥ 1000. |
| `tenure_months` | integer | Conditional | Required if `repayment_type = emi`; 1–60. |
| `lumpsum_tenure_months` | integer | Conditional | Required if `repayment_type = lumpsum`; 1–24. |
| `purpose` | string | Yes | Max 255. |
| `description` | string | No | |
| `document_path` | file | No | Max 2048 KB, any mime (no `mimes:` restriction despite the field name suggesting a "path"). |

**File uploads:** field `document_path`; stored on the public disk at `public/uploads/loan/`.

**Success response — `200`:** `{"success": true, "message": "Loan request submitted successfully and pending approval"}` (or `"Loan approved successfully"` if the category doesn't require approval).

**Error responses:**
| Status | Condition | Body |
|---|---|---|
| 200 | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| 200 | Amount exceeds category's `max_amount` | `{"success": false, "message": "Amount exceeds maximum limit of ₹{max_amount}"}` |
| 200 | EMI tenure exceeds category's `max_tenure_months` | `{"success": false, "message": "Tenure exceeds maximum limit of {N} months", "max_tenure": N}` |
| 500 | Unhandled | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Special behavior / notes:** EMI is computed with a standard amortization formula (`monthlyRate` from `interest_rate/100/12`); first EMI date is pinned to the 7th of the current or next month depending on submission day. Lump-sum loans compute total payable with flat simple interest (`amount * rate * months / 1200`) due in one installment on `lumpsum_due_date`. If `category.requires_approval` is false, the loan is auto-approved and its full repayment schedule (`loan_repayments` rows) is generated immediately in the same request.

---

### GET /api/loan/show/{id}
**Purpose:** Full detail of a single loan — amounts, approval trail, progress, and full repayment schedule.
**Auth:** JWT + Device-Token. **Permission/role required:** inline check — `admin`/`hr`/`manager` (plain `role` column) can view any tenant loan; any other role is restricted to their own (`user_id = auth()->id()`).

**Success response — `200`:**
```json
{
  "success": true, "message": "Data fetched successfully.",
  "data": {
    "id": 12, "loan_number": "LN-000012", "amount": "50,000.00", "interest_rate": "10%", "tenure_months": 12,
    "emi_amount": "4,500.00", "remaining_amount": "45,500.00", "repayment_type": "emi", "status": "active",
    "status_label": "Active", "purpose": "Medical", "description": null, "document_path": "uploads/loan/xyz.pdf",
    "loan_date": "2026-08-01", "first_emi_date": "2026-09-07", "lumpsum_due_date": null, "lumpsum_amount": null,
    "closed_date": null, "created_at": "...", "updated_at": "...",
    "category": "Personal Loan",
    "approval": {"approved_by": "Vikram Shah", "approved_at": "...", "rejected_by": null, "rejected_at": null, "rejection_reason": null, "disbursed_by": null, "disbursed_at": null, "cancelled_by": null, "cancelled_at": null, "cancellation_reason": null},
    "progress": {"percentage": 10.0, "paid_amount": "4,500.00", "remaining_amount": "45,500.00", "next_due_emi": {"installment_number": 2, "due_date": "2026-10-07", "amount": "4,500.00"}},
    "repayment_schedule": {
      "summary": {"total_installments": 12, "paid_installments": 1, "pending_installments": 11, "overdue_installments": 0, "total_paid": "4,500.00", "remaining_to_pay": "45,500.00"},
      "installments": [{"installment_number": 1, "month": "2026-09", "due_date": "2026-09-07", "emi_amount": "4,500.00", "principal_amount": "4,166.67", "interest_amount": "333.33", "total_amount": "4,500.00", "paid_amount": "4,500.00", "status": "paid", "status_label": "Paid", "paid_date": "2026-09-07", "is_overdue": false}]
    }
  }
}
```
`progress` is only present when `status = "active"`; `repayment_schedule` is `null` if no installments exist.

**Error responses:** `500 {"success": false, "message": "An error occurred. Please try again later."}` (covers both "not found" via `findOrFail` and genuine server errors — no distinct 404 shape).
