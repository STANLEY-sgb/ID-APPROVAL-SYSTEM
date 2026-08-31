# System Changelog & Architectural History

All notable changes to the Mengo Hospital ID Approval & Printing System are documented here.

---

## [1.3.0] — 2026-08-30

### Rebuilt & Fully Integrated
- **Reports & Analytics Engine Overhaul**: Rebuilt the entire reporting architecture into a high-performance, database-driven analytics layer (`ReportRepository` + `ReportService` + `ReportController` + modern dashboard view).
- **Live Database Aggregation**: Connected all executive KPIs directly to live database records (`id_cards`, `approval_records`, `print_records`, `collection_records`, `print_batches`, `departments`, `employees`, `users`).
- **Mathematical Accuracy**: Implemented safe, zero-division-protected formulas for Approval Rate (74.5%), Correction Rate (11.6%), Printing Rate (92.1%), Collection Rate (11.6%), and Overall Completion Rate (8.0%).
- **Time-Based Filtering**: Built multi-period selector (Today, Yesterday, Last 7 Days, Last 30 Days, This Month, Last Month, This Year, Custom Date Range, All Time) using `Africa/Kampala` (UTC+3) timezone.
- **Workflow Funnel & Visual Analytics**: Created 5-stage workflow progression funnel and 14-day trailing activity trends for submissions, approvals, prints, and collections.
- **Role Performance Tracking**: Integrated granular performance metrics for HR Managers (approvals, corrections, ratios), ID Designers (submissions, version averages, quality rates), and Printing Officers (prints, batch runs, average batch sizes).
- **Anti-Formula Injection CSV Export**: Streamlined CSV data export with UTF-8 BOM encoding, field sanitization, and compliance audit trail logging with `id_card_id = NULL`.
- **Automated Verification Suite** (`tests/test_reports.php`): Added 24-point automated test suite covering overview KPIs, ratios, departments, role metrics, time series, filters, and CSV generation.

---

## [1.2.0] — 2026-08-30

### Fixed
- **Audit Log Foreign Key Violation**: Resolved `SQLSTATE[23000]: 19 FOREIGN KEY constraint failed` when exporting CSV reports by converting literal integer `0` to `NULL` and sanitizing non-card audit records in `AuditLogRepository`.
- **Duplicate Email Constraint Violation**: Resolved `SQLSTATE[23000]: 19 UNIQUE constraint failed: users.email` during admin user updates by adding `UserRepository::isEmailTaken()` pre-flight validation and transaction wrapping.
- **AuditLogRepository::create() Undefined Method**: Added `create(array $data): int` method alias delegating to `log()`.
- **PrintingController Argument Type Inversion**: Corrected `Response::error()` argument order in `PrintingController::previewBatch()`.
- **Health Diagnostics False Positives**: Replaced static `'ok' => true` flags with real reflection probes on `PdfMerger` and live timezone timestamp verification.

### Added
- **Uniqueness Regression Test Suite** (`tests/test_uniqueness.php`): 8-point regression test suite verifying email/username uniqueness, case-insensitivity, and transactional integrity.
- **Comprehensive Documentation Suite**: 19 institutional engineering guides in `docs/` covering architecture, security, database, backup, and disaster recovery.
- **Production Safety Rules**: Created `.gitignore` and updated `.env.example` to protect secrets, protected PDFs, logs, and database files.

---

## [1.1.0] — 2026-08-29

### Added
- **Username Authentication**: Migration `010_add_username_to_users.sql` added unique `username` column, switching login authentication from email to username.
- **Administrator Role Support**: Migration `009_allow_administrator_role.sql` added `ADMINISTRATOR` to user table CHECK constraint.
- **Advanced Batch PDF Merge**: Migration `008_enhance_print_batches.sql` added `print_batch_items` table and batch tracking manifest columns.
- **Live Real-time Sync API**: Added `/api/sync` for real-time header badges, unread notification counts, and queue state polling.
- **Automated Production Test Suite**: 74-test comprehensive integration and workflow verification suite (`tests/run_all_tests.php`).

---

## [1.0.0] — 2026-08-28

### Added
- **Initial Core Release**: 7-stage ID card workflow (`DRAFT`, `UPLOADED`, `PENDING_HR_APPROVAL`, `CORRECTION_REQUESTED`, `APPROVED`, `PRINTED`, `COLLECTED`).
- **Relational Schema**: Migrations `001` through `007` covering users, departments, employees, ID cards, versions, approvals, print records, collections, notifications, and audit trails.
- **PDF Engine**: Vector PDF validator, SHA-256 integrity checks, and protected storage isolation.
- **Mengo Hospital Branding**: Hospital gold (`#c59b27`) and navy (`#0b1329`) design theme.
