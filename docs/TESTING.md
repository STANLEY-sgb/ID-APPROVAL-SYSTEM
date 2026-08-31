# Testing & Automated Verification

## 1. Test Suite Architecture

The testing framework consists of standalone PHP test runners that exercise the application without requiring heavy external dependencies.

---

## 2. Test Suites Overview

### 2.1 Automated Production Test Suite (`tests/run_all_tests.php`)
Executes **74 automated tests** across 12 operational domains:

1. **Database & Storage Architecture** (4 tests): SQLite PDO connection, WAL journal mode, foreign-key enforcement, PRAGMA integrity check.
2. **Security & Authentication** (12 tests): Password hashing, bad password rejection, username authentication for all seeded users, CSRF validation & rejection, HTML sanitization.
3. **Role-Based Access Control** (8 tests): Strict role boundary verification across Designer, HR Manager, Printing Officer, and Administrator.
4. **End-to-End Workflow Lifecycle** (11 tests): Complete progression (`DRAFT` → `UPLOADED` → `CORRECTION` → `REUPLOAD` → `APPROVE` → `PRINT` → `COLLECT`), version increments, approver audit trails.
5. **Atomic CAS Concurrency Control** (2 tests): Multi-HR manager collision simulation, double-approval prevention, winner conflict message validation.
6. **PDF Security & SHA-256 Checksum** (2 tests): Integrity verification of stored protected PDFs, tamper detection on modified bytes.
7. **Audit Trail Immutability** (6 tests): Audit logging of all lifecycle transitions.
8. **Persistent Notifications** (1 test): Designer receipt of correction notices.
9. **Safe WAL Database Backup Service** (3 tests): Online backup generation, disk verification, backup byte-size validation.
10. **Bulk Printing Engine & Batch Validation** (6 tests): Rejection of mixed/ineligible batches, batch reference creation, bulk status transitions.
11. **Smart Follow-up Attention Thresholds** (5 tests): Calculation of overdue approvals, stale corrections, printing delays, collection delays.
12. **Advanced Batch PDF Merge & Physical Print Handshake** (14 tests): PDF merge validation, page counts, SHA-256 batch output hashing, physical print confirmation, temporary artifact cleanup.

---

### 2.2 Uniqueness Regression Test Suite (`tests/test_uniqueness.php`)
Executes **8 targeted regression tests**:
- Updating user retaining own email (Allowed).
- Reassigning an existing email to another user (Cleanly rejected before SQL).
- Assigning genuinely unique new email (Allowed).
- Username collision checks & case-insensitive matching.
- Transaction rollback on conflict.
- Foreign key safety on non-card audit records (`DATA_EXPORTED` with `id_card_id = NULL`).

### 2.3 Reports & Analytics Verification Suite (`tests/test_reports.php`)
Executes **24 automated tests** covering the reporting and business intelligence layer:
- Live database row verification across executive overview KPIs.
- Mathematical validity of derived metrics (Approval Rate, Correction Rate, Printing Rate, Collection Rate, Overall Completion Rate).
- Aggregate department breakdown across all 11 hospital departments with completion percentages.
- HR Manager workload and approval turnaround analytics.
- ID Designer quality and submission metrics.
- Printing Officer production and batch job statistics.
- Contiguous 14-day time series daily activity trend.
- Time-based date range filtering (Today, Last 7 Days, This Month, All Time).
- Anti-formula injection CSV export generation with UTF-8 BOM encoding.

---

## 3. Running the Test Suites

Execute from the project root:

```bash
# Run complete 74-test production suite
php tests/run_all_tests.php

# Run email & username uniqueness regression suite
php tests/test_uniqueness.php

# Run reports & analytics verification suite
php tests/test_reports.php
```
