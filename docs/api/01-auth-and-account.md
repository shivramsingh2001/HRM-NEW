# Auth & Account API

Covers the mobile/legacy JSON API's authentication and account-lifecycle endpoints (`routes/api.php`, `App\Http\Controllers\Api\Auth\AuthController` and `App\Http\Controllers\Api\FCM\FCMController`). This surface has **no fixed response envelope** — every action returns its own ad hoc JSON shape, almost always `{"success": bool, "message": string, ...}`, and (with the sole exception of validation-via-`abort`/middleware paths) uses HTTP `200` even for business failures such as "invalid password" or "invalid OTP" — this is a deliberate, documented convention for the Flutter client, not a bug. Login/OTP/reset actions are wrapped by `TenantMiddleware` (`tenant` alias); everything except login/forgot-password/reset-password/send-otp/login-otp additionally requires `auth:api` (JWT) + `singleLogin` (Device-Token match).

All URLs below are relative to base URL `https://vpshrms.shurttech.com`.

---

## GET /api/maintenance

**Purpose:** Platform maintenance status — the app calls it on start-up and shows a maintenance screen when `is_active` is `true`. Managed from the Super Admin Panel (Maintenance Mode page).
**Auth:** None. No tenant header, not rate-limited by tenant.
**Controller:** `App\Http\Controllers\Api\MaintenanceController@show` → `MaintenanceModeService::publicPayload()`.

**Response shape is frozen** — older app versions read it; never add, rename or reorder keys.

```json
{
    "success": true,
    "data": {
        "id": 1,
        "is_enabled": false,
        "title": "Under Maintenance",
        "message": "We are currently performing scheduled maintenance. Please check back soon.",
        "start_time": null,
        "end_time": null,
        "allowed_ips": [],
        "allowed_users": [],
        "enabled_by": null,
        "created_at": "2026-10-09T06:55:31.000000Z",
        "updated_at": "2026-10-09T06:55:31.000000Z"
    },
    "is_active": false
}
```

- `is_active` = `is_enabled` **and** now is inside the optional `start_time`/`end_time` window (times in UTC ISO-8601). Use `is_active`, not `is_enabled`, to decide whether to block.
- `allowed_users` are `users.id` values; `enabled_by` is a `super_admins.id`.
- While maintenance is active, every other `/api/*` call (except login/OTP and `api/v1/biometric/*`) returns **503** `{"success": false, "maintenance": true, "title", "message", "end_time"}` with a `Retry-After` header when an end time is set — unless the caller's IP or user is on the allow-list.

---

## POST /api/login

**Purpose:** Authenticate an employee/manager with employee ID + password and issue a JWT.
**Auth:** None (pre-auth). Tenant-selectable route.
**Tenant scope:** Resolved from `X-Tenant` / `X-Tenant-ID` header or `tenant_id` body field (`TenantMiddleware::TENANT_SELECTABLE_ROUTES`). Missing/unresolvable tenant → `400` before the controller runs.
**Permission/role required:** None. Only `role` in `['employee','manager']` may log in via this endpoint (other roles get a business-rule rejection, see below).
**Headers**

| Header | Required | Notes |
|---|---|---|
| `X-Tenant` or `X-Tenant-ID` | Yes (or `tenant_id` in body) | Tenant subdomain or numeric tenant ID |
| `Content-Type` | Yes | `application/json` |

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `employee_id` | string | Yes | `required` — looked up via `AuthRepository::findByEmployeeId()` |
| `password` | string | Yes | `required\|string` (no length rule deliberately — see code comment: password *policy* is enforced only when a password is chosen, in `resetPassword`/`changePassword`) |
| `fcm_token` | string | No | `nullable\|string` — stored for push notifications if present |
| `device_id` | string | No | `nullable\|string` |
| `platform` | string | No | `nullable\|string\|in:android,ios` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/login \
  -H "X-Tenant: acme" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "EMP1001",
    "password": "Secret@123",
    "fcm_token": "fcm-device-token-abc",
    "device_id": "android-uuid-123",
    "platform": "android"
  }'
```

**Success response — 200**
```json
{
  "success": true,
  "message": "Login successfully!!!",
  "data": {
    "token": "<jwt-access-token>",
    "user": {
      "id": 42,
      "company_id": 3,
      "employee_id": "EMP1001",
      "name": "Jane Doe",
      "email": "jane@acme.com",
      "contact": "9876543210",
      "status": 1,
      "email_verified_at": null,
      "last_login_token": "a1B2c3...",
      "created_at": "2026-01-10T09:00:00.000000Z",
      "updated_at": "2026-09-28T10:00:00.000000Z",
      "face_register": 0,
      "role": "employee"
    }
  }
}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `400` | Missing tenant identifier | `{"success": false, "message": "Missing tenant identifier. Include an X-Tenant header with your request."}` |
| `400` | Tenant not found / inactive | `{"success": false, "message": "Company not found or inactive."}` |
| `200` | Validation failure (missing `employee_id`/`password`) | `{"success": false, "message": "<first validator error>"}` |
| `200` | Account locked out (5 consecutive failures, 15-minute decay) | `{"success": false, "message": "Too many failed login attempts. Please try again in N minute(s)."}` |
| `200` | Employee ID not found | `{"success": false, "message": "Employee not found."}` |
| `200` | User role not `employee`/`manager` | `{"success": false, "message": "You are not authorized to login."}` |
| `200` | Wrong password | `{"success": false, "message": "Invalid password."}` |
| `200` | Account inactive (`status != 1`) | `{"success": false, "message": "Account is inactive."}` |
| `429` | `throttle:login` exceeded — 5/min per `employee_id+ip`, or 100/day per IP | `{"success": false, "message": "Too many attempts. Please wait a moment and try again."}` |

**Special behavior / notes:**
- On success, `users.last_login_token` is overwritten with a new random 60-char token; this becomes the value the client must send back as `Device-Token` on every subsequent authenticated request (`singleLogin` middleware) — logging in on a new device silently invalidates any other active session.
- A separate cache-based lockout (`LoginAttemptService`, 5 failures / 15-minute decay, keyed by `employee_id` only) sits underneath the IP-aware `throttle:login` rate limiter — the two are independent controls.
- `fcm_token`, if supplied, is upserted into `users.fcm_tokens` (JSON array, capped at last 5 tokens, de-duplicated by token/device_id).

---

## POST /api/forgot-password

**Purpose:** Request a password-reset email.
**Auth:** None (pre-auth). Tenant-selectable route.
**Tenant scope:** Same as `/api/login` (header/body based).
**Permission/role required:** None.
**Headers:** Same as `/api/login` (`X-Tenant`/`X-Tenant-ID`, `Content-Type: application/json`).

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `email` | string | Yes | `required\|email` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/forgot-password \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{"email": "jane@acme.com"}'
```

**Success response — 200**
```json
{
  "success": true,
  "message": "If that email address is registered, a password reset link has been sent."
}
```
This exact same body is returned whether or not the email exists — deliberate, to prevent account enumeration.

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `400` | Missing/invalid tenant | Same as `/api/login` |
| `200` | Validation failure (missing/invalid email) | `{"success": false, "message": "<first validator error>"}` |
| `500` | Mail send / server error | `{"success": false, "message": "Failed to send reset email. Please try again."}` |
| `429` | `throttle:login` exceeded | Same shape as `/api/login` |

**Special behavior / notes:** Generates a plaintext 60-char token, stores only its bcrypt hash in `password_reset_tokens`, and emails the plaintext token/link (`PasswordResetMail`) to the user. Token is valid 60 minutes (see `/api/reset-password`).

---

## POST /api/reset-password

**Purpose:** Complete a password reset using the emailed token.
**Auth:** None (pre-auth). Tenant-selectable route.
**Tenant scope:** Same as `/api/login`.
**Permission/role required:** None.
**Headers:** Same as `/api/login`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `email` | string | Yes | `required\|email\|exists:users,email` |
| `token` | string | Yes | `required` — plaintext token from the reset email/link |
| `password` | string | Yes | `required\|confirmed\|Password::min(8)->mixedCase()->numbers()` — requires a matching `password_confirmation` field |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/reset-password \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{
    "email": "jane@acme.com",
    "token": "<plaintext-token-from-email>",
    "password": "NewSecret123",
    "password_confirmation": "NewSecret123"
  }'
```

**Success response — 200**
```json
{"success": true, "message": "Password has been reset successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `400` | Missing/invalid tenant | Same as `/api/login` |
| `200` | Validation failure (bad email/missing token/weak or unconfirmed password) | `{"success": false, "message": "<first validator error>"}` |
| `200` | No reset record for that email | `{"success": false, "message": "Invalid password reset request"}` |
| `200` | Token doesn't match stored hash | `{"success": false, "message": "Invalid or expired token"}` |
| `200` | Token older than 60 minutes (record is deleted) | `{"success": false, "message": "Token has expired. Please request a new password reset."}` |
| `200` | User record no longer exists | `{"success": false, "message": "User not found"}` |
| `500` | Server error | `{"success": false, "message": "An error occurred. Please try again later."}` |
| `429` | `throttle:login` exceeded | Same shape as `/api/login` |

**Special behavior / notes:** Tries both `urldecode` and `rawurldecode` of the submitted token before rejecting it (handles double-encoding from some email clients). Deletes the reset-token row after either a successful reset or an expiry check.

---

## POST /api/send-otp

**Purpose:** Send a 6-digit SMS OTP (via Airtel IQ SMS) to a registered mobile number, for OTP-based login.
**Auth:** None (pre-auth). Tenant-selectable route.
**Tenant scope:** Same as `/api/login`.
**Permission/role required:** None. Only users with role `employee` or `manager` and a matching `contact` are found.
**Headers:** Same as `/api/login`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `mobile_no` | string/number | Yes | `required\|numeric\|digits:10\|exists:users,contact` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/send-otp \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{"mobile_no": "9876543210"}'
```

**Success response — 200**
```json
{"success": true, "message": "Otp Sent Successfully!!!"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `400` | Missing/invalid tenant | Same as `/api/login` |
| `200` | Validation failure (not 10 digits / number not in `users.contact`) | `{"success": false, "message": "<first validator error>"}` |
| `200` | No employee/manager account with that contact | `{"success": false, "message": "Account not found. !!!"}` |
| `200` | An OTP was already sent < 30 seconds ago | `{"success": false, "message": "Please wait before requesting another OTP."}` (note: this specific branch returns default `200` with **no explicit status code call**, same net effect) |
| `200` | Airtel SMS API call failed | `{"success": false, "message": "Failed to Send OTP"}` |
| `500` | Unhandled server error | `{"success": false, "message": "An error occurred while sending OTP."}` |
| `429` | `throttle:otp-request` exceeded — 3/min or 10/day per `mobile_no` | `{"success": false, "message": "Too many attempts. Please wait a moment and try again."}` |

**Special behavior / notes:** OTP is a random 6-digit code, stored in the `otps` table with a 2-minute expiry. The OTP value itself is never returned in the response (commented out in code). SMS is sent through Airtel's `iqsms.airtel.in` API using tenant-agnostic credentials from `config('sms.airtel.*')`.

---

## POST /api/login-otp

**Purpose:** Complete OTP login (verify the OTP sent via `/api/send-otp`) and issue a JWT.
**Auth:** None (pre-auth). Tenant-selectable route.
**Tenant scope:** Same as `/api/login`.
**Permission/role required:** None (same role restriction as `/api/send-otp`).
**Headers:** Same as `/api/login`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `mobile_no` | string/number | Yes | `required\|numeric\|digits:10` |
| `otp` | string/number | Yes | `required\|numeric\|digits:6` |
| `fcm_token` | string | No | `nullable\|string` |
| `device_id` | string | No | `nullable\|string` |
| `platform` | string | No | `nullable\|string\|in:android,ios` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/login-otp \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{"mobile_no": "9876543210", "otp": "482913", "fcm_token": "fcm-token-abc", "platform": "android"}'
```

**Success response — 200**
```json
{
  "success": true,
  "message": "Login Successfully",
  "data": {
    "token": "<jwt-access-token>",
    "user": {
      "id": 42, "company_id": 3, "employee_id": "EMP1001", "name": "Jane Doe",
      "email": "jane@acme.com", "contact": "9876543210", "status": 1,
      "email_verified_at": null, "last_login_token": "a1B2c3...",
      "created_at": "2026-01-10T09:00:00.000000Z", "updated_at": "2026-09-28T10:00:00.000000Z",
      "face_register": 0, "role": "employee"
    }
  }
}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `400` | Missing/invalid tenant | Same as `/api/login` |
| `200` | Validation failure | `{"success": false, "message": "<first validator error>"}` |
| `200` | Locked out (5 failed OTP checks, cache key `otp:{mobile_no}`) | `{"success": false, "message": "Too many failed login attempts. Please try again in N minute(s)."}` |
| `200` | OTP doesn't match / already used | `{"success": false, "message": "Invalid OTP."}` |
| `200` | OTP expired (> 2 minutes old) | `{"success": false, "message": "OTP has expired."}` |
| `200` | No matching employee/manager account | `{"success": false, "message": "Account not found"}` (no explicit status code — defaults to 200) |
| `200` | Account inactive | `{"success": false, "message": "Account is inactive."}` |
| `500` | Server error | `{"success": false, "message": "Login failed due to server error.<exception message appended>"}` |
| `429` | `throttle:otp-verify` exceeded — 5/min per `mobile_no` | `{"success": false, "message": "Too many attempts. Please wait a moment and try again."}` |

**Special behavior / notes:** Same single-device-login mechanics as `/api/login` (`last_login_token` reissued). The lockout here uses the shared `LoginAttemptService` under the key `otp:{mobile_no}`, independent from the `/api/login` lockout keyed by `employee_id`.

---

## GET /api/verify-token

**Purpose:** Confirm the caller's JWT is valid and return the authenticated user object; defined inline in `routes/api.php`, not a controller method.
**Auth:** JWT bearer (`Authorization: Bearer <token>`) + Device-Token header (`auth:api`, `singleLogin`).
**Tenant scope:** Resolved from the authenticated JWT user's `tenant_id`.
**Permission/role required:** None beyond being authenticated.
**Headers**

| Header | Required | Notes |
|---|---|---|
| `Authorization` | Yes | `Bearer <jwt>` |
| `Device-Token` | Yes | Must match `users.last_login_token` |

**Request parameters / body:** None
**File uploads:** None

**Sample request**
```bash
curl -X GET https://vpshrms.shurttech.com/api/verify-token \
  -H "Authorization: Bearer <jwt>" \
  -H "Device-Token: a1B2c3..."
```

**Success response — 200**
```json
{"status": "success", "user": { "...full Eloquent User model attributes..." }}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid/expired JWT | `{"message": "Unauthenticated."}` |
| `401` | Device-Token mismatch | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |

---

## ANY /api/logout/{fcm_token?} and POST /api/logout

**Purpose:** Log out the current session (invalidate the JWT, optionally scrub one FCM token). Both routes map to the same `AuthController::logout`.
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`.

**Request parameters / body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `fcm_token` | string | No | Route param (`/api/logout/{fcm_token}`), query string, or body — first non-null value wins. If supplied and present in `users.fcm_tokens`, that single token is removed. |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/logout \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..." \
  -H "Content-Type: application/json" \
  -d '{"fcm_token": "fcm-device-token-abc"}'
```

**Success response — 200**
```json
{"success": true, "message": "Logged out successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid JWT | `{"message": "Unauthenticated."}` |
| `401` | Device-Token mismatch | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |
| `200` | `Auth::user()` somehow null after passing middleware (defensive check) | `{"success": false, "message": "Unauthenticated"}` |
| `500` | Server error | `{"success": false, "message": "An error occurred during logout"}` |

**Special behavior / notes:** Calls `auth('api')->logout()` to blacklist the current JWT.

---

## GET /api/delete

**Purpose:** Soft-delete (deactivate) the authenticated user's own account.
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`.
**Request parameters / body:** None. **File uploads:** None.

**Sample request**
```bash
curl -X GET https://vpshrms.shurttech.com/api/delete \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..."
```

**Success response — 200**
```json
{"success": true, "message": "Account delete successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid JWT | `{"message": "Unauthenticated."}` |
| `401` | Device-Token mismatch | `{"status": false, "message": "Your account is logged in on another device. Please login again."}` |
| `200` | `Auth::user()` null (defensive) | `{"success": false, "message": "Unauthenticated"}` |
| `500` | Server error | `{"success": false, "message": "An error occurred during logout"}` (message text is copy-pasted from the logout action, not corrected for this endpoint) |

**Special behavior / notes:** This is a **soft delete** — it sets `users.status = 0` (same flag as "inactive"), no row is actually removed. The JWT is NOT invalidated by this call.

---

## POST /api/fcm/token

**Purpose:** Register/update an FCM push token for the authenticated user's current device.
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`, `Content-Type: application/json`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `fcm_token` | string | Yes | `required\|string` |
| `device_id` | string | No | `nullable\|string` |
| `platform` | string | No | `nullable\|string\|in:android,ios` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/fcm/token \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..." -H "Content-Type: application/json" \
  -d '{"fcm_token": "fcm-device-token-abc", "device_id": "android-uuid-123", "platform": "android"}'
```

**Success response — 200**
```json
{"success": true, "message": "FCM token stored successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid JWT or Device-Token mismatch | Same as above |
| `422` | Validation failure | `{"success": false, "errors": {"fcm_token": ["The fcm token field is required."]}}` (full Laravel validator error-bag shape, field name → array of messages) |
| `500` | Server error | `{"success": false, "message": "Failed to store token: <exception message>"}` |

**Special behavior / notes:** Appends the token to `users.fcm_tokens` (JSON array) and de-duplicates by exact token value only (`unique('token')`) — unlike the login/OTP flows, this action does **not** cap the array at 5 entries or dedupe by `device_id`.

---

## DELETE /api/fcm/token

**Purpose:** Remove a specific FCM push token for the authenticated user (e.g. on logout from one device).
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`, `Content-Type: application/json`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `fcm_token` | string | Yes | `required\|string` |

**File uploads:** None

**Sample request**
```bash
curl -X DELETE https://vpshrms.shurttech.com/api/fcm/token \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..." -H "Content-Type: application/json" \
  -d '{"fcm_token": "fcm-device-token-abc"}'
```

**Success response — 200**
```json
{"success": true, "message": "FCM token removed successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid JWT or Device-Token mismatch | Same as above |
| `422` | Validation failure (missing `fcm_token`) | `{"success": false, "errors": {"fcm_token": ["The fcm token field is required."]}}` |
| `500` | Server error | `{"success": false, "message": "Failed to remove token: <exception message>"}` |

---

## GET /api/user/face-registration

**Purpose:** Mark the authenticated user as having completed face registration (used by the mobile app's biometric face-capture onboarding flow).
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`.
**Request parameters / body:** None. **File uploads:** None (despite the name, this endpoint does not accept an image — it only flips a boolean flag on `user_job_details.face_register`).

**Sample request**
```bash
curl -X GET https://vpshrms.shurttech.com/api/user/face-registration \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..."
```

**Success response — 200**
```json
{"success": true, "message": "Face registered successfully", "data": {"face_register": 1}}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `200` | `Auth::user()` null (defensive) | `{"success": false, "message": "Unauthorized"}` |
| `200` | Already registered | `{"success": false, "message": "Already face registered.", "data": {"face_register": 1}}` |
| `401` | Missing/invalid JWT or Device-Token mismatch | Standard 401 bodies |
| `500` | Server error | `{"success": false, "message": "An error occured.Please try again later."}` (typo is in the source) |

**Special behavior / notes:** `updateOrCreate`s a `user_job_details` row keyed by `user_id`, setting `face_register = 1`. Idempotent-in-intent but the second call returns `success:false` rather than repeating the success body.

---

## POST /api/change-password

**Purpose:** Change the authenticated user's password (requires current password).
**Auth:** JWT bearer + Device-Token header.
**Tenant scope:** From authenticated user.
**Permission/role required:** None beyond being authenticated.
**Headers:** `Authorization: Bearer <jwt>`, `Device-Token: <token>`, `Content-Type: application/json`.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `current_password` | string | Yes | `required` |
| `new_password` | string | Yes | `required\|different:current_password\|Password::min(8)->mixedCase()->numbers()` |
| `confirm_password` | string | Yes | `required\|same:new_password` |

**File uploads:** None

**Sample request**
```bash
curl -X POST https://vpshrms.shurttech.com/api/change-password \
  -H "Authorization: Bearer <jwt>" -H "Device-Token: a1B2c3..." -H "Content-Type: application/json" \
  -d '{"current_password": "OldSecret1", "new_password": "NewSecret2", "confirm_password": "NewSecret2"}'
```

**Success response — 200**
```json
{"success": true, "message": "Password changed successfully"}
```

**Error responses**

| HTTP status | Condition | Body |
|---|---|---|
| `401` | Missing/invalid JWT or Device-Token mismatch | Standard 401 bodies |
| `200` | Validation failure (weak/same/mismatched password) | `{"success": false, "message": "<first validator error>"}` — custom messages: `"New password must be different from current password"`, `"Confirm password must match new password"` |
| `200` | Wrong current password | `{"success": false, "message": "Current password is incorrect"}` |
| `500` | Server error | `{"success": false, "message": "An error occurred. Please try again later."}` |

**Special behavior / notes:** Runs inside a DB transaction; logs the change via `AuthAuditService::logPasswordChanged()`. Does **not** invalidate the current JWT or force re-login.
