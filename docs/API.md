# API Reference & Endpoints

## 1. Overview & Authentication

The Mengo Hospital ID Approval System exposes focused internal API endpoints designed for real-time live synchronization and interactive batch validation. All API endpoints require an active, authenticated session.

---

## 2. API Endpoints

### 2.1 Live Synchronization Endpoint
- **URL**: `GET /api/sync`
- **Authentication**: Required (`AuthMiddleware`)
- **Headers**: `Accept: application/json`

#### Response (`200 OK`)
```json
{
  "success": true,
  "timestamp": "30 August 2026 at 00:45:12",
  "unread_notifications": 3,
  "pending_approvals_count": 5,
  "ready_to_print_count": 12,
  "corrections_count": 1,
  "smart_alerts": {
    "overdue_approvals": 2,
    "stale_corrections": 0,
    "printing_delays": 1,
    "collection_delays": 4
  }
}
```

---

### 2.2 Batch Validation Endpoint
- **URL**: `POST /printing/batches/validate`
- **Authentication**: Required (Role: `PRINTING_OFFICER`)
- **Headers**: `Content-Type: application/json`, `X-CSRF-Token: <token>`

#### Request Body
```json
{
  "card_ids": [4, 7, 12, 19]
}
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "total_selected": 4,
  "valid_count": 4,
  "failed_count": 0,
  "items": [
    {"id_card_id": 4, "status": "VALID", "employee_name": "Dr. Sarah Mukasa"},
    {"id_card_id": 7, "status": "VALID", "employee_name": "Nurse Peter Ssemwanga"},
    {"id_card_id": 12, "status": "VALID", "employee_name": "John Kato"},
    {"id_card_id": 19, "status": "VALID", "employee_name": "Grace Nakato"}
  ]
}
```

---

### 2.3 System Health Endpoint
- **URL**: `GET /health?format=json`
- **Authentication**: Required
- **Headers**: `Accept: application/json`

#### Response (`200 OK`)
```json
{
  "status": "healthy",
  "timestamp": "30 August 2026 at 00:45:12",
  "app": "Mengo Hospital ID Management System",
  "environment": "production",
  "checks": [
    {"name": "PHP Runtime Engine", "ok": true, "detail": "v8.2.10 (Running on Linux)"},
    {"name": "SQLite Database Integrity", "ok": true, "detail": "Integrity: ok | WAL: wal | FK: ON"},
    {"name": "Database Transaction Read/Write", "ok": true, "detail": "Atomic read/write completed in < 1ms"},
    {"name": "Protected PDF Storage", "ok": true, "detail": "storage/uploads/protected (Writable)"},
    {"name": "Temporary Batch Merge Storage", "ok": true, "detail": "storage/temp (Writable)"},
    {"name": "PDF Processing & Merge Engine", "ok": true, "detail": "PdfMerger loaded (6 methods available)"},
    {"name": "Hospital Timezone & Clock", "ok": true, "detail": "30 August 2026 at 00:45:12 EAT (UTC+3)"}
  ]
}
```
