# System Health, Diagnostics & Observability

## 1. System Health Endpoint (`/health`)

The application provides a comprehensive live diagnostic monitor (`src/Controllers/HealthController.php`).

> [!IMPORTANT]
> The health check engine **NEVER** reports `OPERATIONAL` merely because configuration keys exist. Every check executes a real, active verification against the underlying runtime component.

```mermaid
graph TD
    Health[/health Request] --> PHPCheck[1. PHP 8.0+ Runtime Check]
    Health --> DBIntegrity[2. SQLite PRAGMA integrity_check]
    Health --> DBTransaction[3. Atomic INSERT + DELETE Test]
    Health --> StorageCheck[4. Protected Storage Writable Probe]
    Health --> TempCheck[5. Temp Merge Directory Writable Probe]
    Health --> PDFEngine[6. PdfMerger Class & Reflection Probe]
    Health --> ClockCheck[7. EAT Timezone & Clock Parse Test]
```

### Monitored Subsystems & Diagnostic Checks
1. **PHP Runtime Engine**: Verifies PHP version $\ge 8.0.0$ and active extensions.
2. **Database Integrity**: Executes `PRAGMA integrity_check`, verifies WAL journal mode and foreign key enforcement status.
3. **Database Write Transaction**: Executes a live atomic `INSERT` into `audit_logs` and immediate `DELETE` to confirm write capability.
4. **Protected Storage**: Verifies that `storage/uploads/protected/` exists and is writable.
5. **Temporary Storage**: Verifies that `storage/temp/` exists and is writable.
6. **PDF Merge Engine**: Uses PHP `ReflectionClass` to confirm `PdfMerger` class is compiled and all merge methods are callable.
7. **Hospital Clock**: Confirms `Africa/Kampala` (UTC+3) server offset and timestamp parsing.

---

## 2. Structured Application Logging

Logs are written to `storage/logs/` with strict security filtering (passwords and session IDs are scrubbed):

| Log File | Purpose |
| :--- | :--- |
| `storage/logs/app.log` | Uncaught exceptions, system fatal errors, and stack traces. |
| `storage/logs/email.log` | SMTP delivery attempts, recipient addresses, and transport status. |
| `storage/logs/security.log` | Failed login attempts, lockout triggers, and forbidden access attempts. |
