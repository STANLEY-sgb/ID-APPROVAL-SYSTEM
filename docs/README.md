# Mengo Hospital ID Approval & Printing System — Engineering Documentation

Welcome to the comprehensive technical documentation for the **Mengo Hospital Employee ID Card Approval & Printing System**. This repository contains the complete specification, architecture, installation, configuration, security, workflow, database, deployment, and disaster recovery manuals for the application.

---

## 📚 Documentation Index

| Document | Description |
| :--- | :--- |
| **[System Architecture](SYSTEM_ARCHITECTURE.md)** | Full layered architecture overview (Browser → Router → Middleware → Controller → Service → Repository → Storage/DB). |
| **[Installation Guide](INSTALLATION.md)** | Step-by-step installation instructions for local development and server staging. |
| **[Configuration Reference](CONFIGURATION.md)** | Environment variables, `.env` settings, and runtime configurations. |
| **[Database Architecture](DATABASE.md)** | Schema details, tables, constraints, WAL mode, indexes, and migration paths to MySQL/PostgreSQL. |
| **[Security Architecture](SECURITY.md)** | Cryptography, CSRF, XSS, rate-limiting, session lifecycle, and input sanitization. |
| **[Authentication & RBAC](AUTHENTICATION_AND_RBAC.md)** | Username/Password login, role permissions matrix, password hashing, and session management. |
| **[Workflow State Machine](WORKFLOW.md)** | Complete 7-stage ID lifecycle (DRAFT → UPLOADED → APPROVAL → PRINTING → COLLECTION) and CAS concurrency. |
| **[PDF & Printing Engine](PDF_AND_PRINTING.md)** | Vector PDF processing, SHA-256 integrity, batch merge engine, and secure storage isolation. |
| **[Notifications System](NOTIFICATIONS.md)** | In-app notification queues, multi-role targeting, real-time sync polling, and optional SMTP mail. |
| **[Audit Logging](AUDIT_LOGGING.md)** | Immutable compliance audit trails, forensic event capturing, IP/User-Agent tracking, and NULL entity handling. |
| **[API Reference](API.md)** | Real-time live synchronization (`/api/sync`) and batch preview endpoints. |
| **[Testing & Verification](TESTING.md)** | 74-test automated suite, regression tests, CAS simulation, and test execution instructions. |
| **[Production Deployment](DEPLOYMENT.md)** | Linux / Apache / Nginx deployment guide, SSL/TLS, webroot hardening, and permissions. |
| **[Backup & Recovery](BACKUP_AND_RECOVERY.md)** | Online SQLite backup API, file system replication, and hot backup procedures. |
| **[Monitoring & Health](MONITORING.md)** | Health diagnostics endpoint (`/health`), structured logging, and observability metrics. |
| **[Troubleshooting Guide](TROUBLESHOOTING.md)** | Symptom, cause, diagnosis, and fix guide for historical and runtime errors. |
| **[Maintenance Guide](MAINTENANCE.md)** | Day-to-day operations, user provisioning, database migrations, and secret rotation. |
| **[Changelog](CHANGELOG.md)** | Detailed chronological log of bug fixes, schema enhancements, and architectural revisions. |
| **[Disaster Recovery](DISASTER_RECOVERY.md)** | Comprehensive disaster recovery plan, RPO/RTO targets, and step-by-step restoration playbooks. |

---

## 🏥 Hospital Overview & System Purpose

The **Mengo Hospital ID Approval & Printing System** is an institutional software platform designed to manage the end-to-end lifecycle of staff identification cards for Mengo Hospital. 

### Key Business Goals
1. **Quality & Standard Compliance**: Eliminate printing errors by enforcing a mandatory multi-point checklist review by Human Resource Managers before an ID can reach the physical printer.
2. **Anti-Fraud & Audit Compliance**: Track every action (upload, review, approval, batching, print output, and physical badge handover) in an immutable, tamper-evident audit log.
3. **High Efficiency**: Enable Printing Officers to validate, merge, and batch-print dozens of approved ID cards into unified multi-page print jobs with automatic page-ordering and vector-fidelity preservation.
4. **Data Privacy**: Ensure that sensitive employee personal data and PDF assets remain strictly shielded behind server-side role-based authorization barriers and protected non-public storage directories.
