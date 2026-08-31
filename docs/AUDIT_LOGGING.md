# Audit Trail & Forensic Compliance Logging

## 1. Compliance Principles

Hospital identification records must satisfy strict institutional governance and audit standards. The audit log system tracks:
1. **Who**: Authenticated staff member ID, name, and role.
2. **What**: Exact action performed (`ID_UPLOADED`, `ID_APPROVED`, `DATA_EXPORTED`, `USER_CREATED`...).
3. **When**: Precise timestamp recorded in East Africa Time (`Africa/Kampala`).
4. **Where**: Client IP address and User-Agent string.
5. **State Delta**: Previous status and new status.

---

## 2. Audit Log Schema & Constraints

Audit records are persisted in the `audit_logs` table.

```sql
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NULL REFERENCES id_cards(id) ON DELETE SET NULL,
    user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    user_name VARCHAR(150) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL DEFAULT 'ID_CARD',
    entity_id INTEGER NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    version_number INTEGER NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL
);
```

### Relational Integrity Rules
- **Foreign Key Safety**: For non-card events (such as user management, system exports, and logins), `id_card_id` **MUST** be passed as `NULL`. Never insert fake placeholder foreign keys (such as `0`).
- **User Integrity**: If a user account is deleted in the future, the audit log retains the immutable `user_name` snapshot while setting `user_id` to `NULL`.

---

## 3. Audited System Events

| Action Code | Trigger Event | Entity Type | Captured Details |
| :--- | :--- | :--- | :--- |
| `ID_UPLOADED` | Initial PDF artwork uploaded | `ID_CARD` | Card ref, version number, SHA-256 hash. |
| `CORRECTION_REQUESTED`| HR returns ID with notes | `ID_CARD` | Specific correction reasons. |
| `ID_REUPLOADED` | Corrected artwork uploaded | `ID_CARD` | Increment version number, new hash. |
| `ID_APPROVED` | HR formal sign-off | `ID_CARD` | 6-point checklist verification status. |
| `BATCH_PRINT_CREATED` | Printing Officer creates batch | `PRINT_BATCH` | Batch reference, total card count. |
| `ID_PRINTED` | Card marked physically printed| `ID_CARD` | Printer metadata, batch link. |
| `ID_COLLECTED` | Badge handed to employee | `ID_CARD` | Recipient identity & verification notes. |
| `USER_CREATED` | New user provisioned by Admin | `USER` | Staff ID, username, assigned role. |
| `USER_ACCOUNT_UPDATED`| User details edited by Admin | `USER` | Modified fields. |
| `DATA_EXPORTED` | Reports exported to CSV | `SYSTEM` | Export criteria, record count. |
| `DATABASE_BACKUP` | SQLite hot backup created | `SYSTEM` | Backup filename and byte size. |
