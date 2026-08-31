-- Migration 007: Create Print Batches Table for Bulk Printing
CREATE TABLE IF NOT EXISTS print_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_reference VARCHAR(64) NOT NULL UNIQUE,
    printing_user_id INTEGER NOT NULL,
    printing_user_name VARCHAR(255) NOT NULL,
    total_cards INTEGER NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (printing_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_print_batches_created_at ON print_batches(created_at);
CREATE INDEX IF NOT EXISTS idx_print_batches_user ON print_batches(printing_user_id);

-- Add print_batch_id column to print_records if not present
-- Note: SQLite supports ADD COLUMN
ALTER TABLE print_records ADD COLUMN print_batch_id INTEGER NULL REFERENCES print_batches(id) ON DELETE SET NULL;
