# Authentication & Role-Based Access Control (RBAC)

## 1. Authentication Mechanics

Users authenticate via official credentials using **Username + Password**:

1. **Username Input**: Supports alphanumeric usernames (e.g. `sarah.namukasa`, `admin`, `designer`).
2. **Password Verification**: Evaluated via `PasswordHasher::verify($plaintext, $hash)`.
3. **Account Status**: Only users with status `ACTIVE` are permitted to authenticate. `INACTIVE` or `SUSPENDED` accounts are rejected immediately.
4. **Session Binding**: Upon authentication, user identity (`id`, `staff_id`, `name`, `email`, `role`, `department`, `force_password_change`) is recorded into `$_SESSION['_auth_user']`.

---

## 2. Definitive Role Permission Matrix

The application defines 4 primary system roles via `Mengo\IdApproval\Models\Role`:

| Action / Capability | `ADMINISTRATOR` | `DESIGNER` | `HR_MANAGER` | `PRINTING_OFFICER` |
| :--- | :---: | :---: | :---: | :---: |
| **Manage Staff User Accounts** | ✅ Full | ❌ | ❌ | ❌ |
| **Toggle Account Status / Reset Password** | ✅ Full | ❌ | ❌ | ❌ |
| **Draft & Upload Initial ID Card PDF** | ❌ | ✅ Full | ❌ | ❌ |
| **Re-upload Corrected ID PDF** | ❌ | ✅ Full | ❌ | ❌ |
| **View Pending HR Approvals** | ❌ | ❌ | ✅ Full | ❌ |
| **Review & Approve ID Card** | ❌ | ❌ | ✅ Full | ❌ |
| **Request ID Design Correction** | ❌ | ❌ | ✅ Full | ❌ |
| **View Ready-to-Print Queue** | ❌ | ❌ | ❌ | ✅ Full |
| **Execute Bulk Batch PDF Merge** | ❌ | ❌ | ❌ | ✅ Full |
| **Mark Card Printed (Handover Prep)** | ❌ | ❌ | ❌ | ✅ Full |
| **Mark Card Collected (Staff Handover)**| ❌ | ❌ | ✅ Full | ❌ |
| **View Real-Time Live Sync (`/api/sync`)**| ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **View System Audit Logs** | ✅ Full | ❌ | ✅ Full | ❌ |
| **Export Reports & Analytics (CSV)** | ✅ Full | ❌ | ✅ Full | ❌ |
| **Create & Download Database Backups** | ✅ Full | ❌ | ✅ Full | ❌ |
| **View System Health & Diagnostics** | ✅ Full | ✅ Full | ✅ Full | ✅ Full |

---

## 3. Server-Side Enforcement

Authorization is enforced strictly on the server by `src/Middleware/AuthMiddleware.php`:

```php
// Enforce single role
AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);

// Enforce multiple permissible roles
AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
```

Attempting to access unauthorized endpoints throws `ForbiddenException` (HTTP 403) and logs a security event to the audit trail.
