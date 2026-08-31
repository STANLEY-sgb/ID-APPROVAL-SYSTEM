# Engineering Troubleshooting Guide

This guide details historical symptoms, underlying root causes, diagnostic methods, and permanent fixes implemented in the codebase.

---

## 1. Issue: `UNIQUE constraint failed: users.email`
- **Symptom**: Editing a user's details in the Admin Panel threw a fatal PDOException: `SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email`.
- **Root Cause**: `AdminController::updateUserAccount()` attempted an unconditional `UPDATE users SET email=?` without pre-checking whether another account held that email.
- **Permanent Fix**: Implemented `UserRepository::isEmailTaken(string $email, ?int $excludeUserId): bool` with case-insensitive `LOWER()` comparison and `id != ?` exclusion. The controller validates email uniqueness prior to SQL execution, wrapping updates in a rollback-safe transaction.

---

## 2. Issue: `FOREIGN KEY constraint failed` on CSV Export
- **Symptom**: Exporting reports to CSV threw `SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed` in `AuditLogRepository`.
- **Root Cause**: `ReportController::exportCsv()` passed literal integer `0` as `$idCardId` to `AuditService::logWorkflow()`. Because SQLite enforces `PRAGMA foreign_keys = ON`, inserting `0` violated the foreign key pointing to `id_cards(id)`.
- **Permanent Fix**: Changed `$idCardId` parameter to `NULL` for all non-card system events. Sanitized `AuditLogRepository::log()` and `AuditService::logWorkflow()` to convert any `$idCardId <= 0` to `NULL` before database insertion.

---

## 3. Issue: `Call to undefined method AuditLogRepository::create()`
- **Symptom**: Fatal error when creating, toggling, or resetting HR user accounts in AdminController.
- **Root Cause**: AdminController invoked `$this->auditRepo->create($data)`, but the repository only implemented `log($data)`.
- **Permanent Fix**: Added `public function create(array $data): int` as a first-class alias method in `AuditLogRepository` delegating directly to `log()`.

---

## 4. Issue: Swapped Argument TypeError in `PrintingController::previewBatch()`
- **Symptom**: TypeError when a requested print batch was missing or deleted.
- **Root Cause**: `PrintingController` invoked `Response::error(404, "Batch not found")` with inverted argument types.
- **Permanent Fix**: Corrected signature call to `Response::error(string $message, int $statusCode = 400)`.

---

## 5. Issue: Health Dashboard Fake Operational Status
- **Symptom**: Health checks reported `OPERATIONAL` for PDF engine and clock even when misconfigured.
- **Root Cause**: Status was hardcoded to `'ok' => true`.
- **Permanent Fix**: Replaced with live PHP `ReflectionClass` probe of `PdfMerger` and active `DateTime` East Africa Time parse verification.
