-- Migration 008: Advanced Batch PDF Merge, Manifest, and Items Schema

-- 1. Create print_batch_items table
CREATE TABLE IF NOT EXISTS print_batch_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER NOT NULL,
    id_card_id INTEGER NOT NULL,
    approved_version_id INTEGER NULL,
    employee_id INTEGER NULL,
    employee_name VARCHAR(255) NOT NULL,
    sequence_number INTEGER NOT NULL DEFAULT 1,
    validation_status VARCHAR(32) NOT NULL DEFAULT 'VALID',
    failure_reason VARCHAR(500) NULL,
    included_in_output INTEGER NOT NULL DEFAULT 1,
    is_printed INTEGER NOT NULL DEFAULT 0,
    printed_at DATETIME NULL,
    FOREIGN KEY (batch_id) REFERENCES print_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_version_id) REFERENCES id_versions(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_batch_items_batch ON print_batch_items(batch_id);
CREATE INDEX IF NOT EXISTS idx_batch_items_card ON print_batch_items(id_card_id);
CREATE INDEX IF NOT EXISTS idx_batch_items_status ON print_batch_items(validation_status);

-- 2. Add extra tracking columns to print_batches safely
-- (SQLite allows ALTER TABLE ADD COLUMN)
-- status: PREPARING, VALIDATING, MERGING, READY, COMPLETED, PARTIAL_SUCCESS, FAILED, EXPIRED
ALTER TABLE print_batches ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'READY';
ALTER TABLE print_batches ADD COLUMN selected_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN valid_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN failed_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN page_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN file_size INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN orientation VARCHAR(32) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE print_batches ADD COLUMN page_size VARCHAR(64) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE print_batches ADD COLUMN output_filename VARCHAR(255) NULL;
ALTER TABLE print_batches ADD COLUMN output_path VARCHAR(500) NULL;
ALTER TABLE print_batches ADD COLUMN output_hash VARCHAR(64) NULL;
ALTER TABLE print_batches ADD COLUMN error_summary TEXT NULL;
ALTER TABLE print_batches ADD COLUMN download_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE print_batches ADD COLUMN completed_at DATETIME NULL;
ALTER TABLE print_batches ADD COLUMN expires_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_print_batches_status ON print_batches(status);
