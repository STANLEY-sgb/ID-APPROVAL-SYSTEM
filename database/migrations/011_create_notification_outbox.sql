-- Migration: 011_create_notification_outbox.sql
-- Adds a transactional notification outbox table for decoupled, async email delivery.
-- Workflow operations write events to this table inside their DB transactions.
-- A separate CLI worker (scripts/process_outbox.php) reads pending rows and delivers emails.

CREATE TABLE IF NOT EXISTS notification_outbox (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type      TEXT    NOT NULL,                       -- e.g. 'ID_APPROVED', 'CORRECTION_REQUESTED'
    to_emails       TEXT    NOT NULL,                       -- JSON array of recipient email addresses
    subject         TEXT    NOT NULL,
    headline        TEXT    NOT NULL,                       -- Email heading / preheader
    body_text       TEXT    NOT NULL,                       -- Plain-text body paragraph
    details_json    TEXT,                                   -- JSON object of key-value detail rows
    id_card_id      INTEGER REFERENCES id_cards(id) ON DELETE SET NULL,
    status          TEXT    NOT NULL DEFAULT 'PENDING',     -- PENDING | PROCESSING | SENT | FAILED
    attempts        INTEGER NOT NULL DEFAULT 0,
    max_attempts    INTEGER NOT NULL DEFAULT 3,
    last_error      TEXT,
    created_at      TEXT    NOT NULL,
    processed_at    TEXT
);

CREATE INDEX IF NOT EXISTS idx_notif_outbox_status
    ON notification_outbox(status, created_at);

CREATE INDEX IF NOT EXISTS idx_notif_outbox_card
    ON notification_outbox(id_card_id);
