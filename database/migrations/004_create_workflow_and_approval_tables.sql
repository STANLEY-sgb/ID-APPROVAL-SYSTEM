-- 004: Create Workflow and Approval Tables

CREATE TABLE IF NOT EXISTS correction_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NOT NULL,
    version_id INTEGER NOT NULL,
    requested_by_user_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING' CHECK(status IN ('PENDING', 'RESOLVED')),
    resolved_version_id INTEGER NULL,
    requested_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id) REFERENCES id_versions(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (resolved_version_id) REFERENCES id_versions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS approval_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NOT NULL UNIQUE,
    version_id INTEGER NOT NULL,
    hr_user_id INTEGER NOT NULL,
    hr_name VARCHAR(150) NOT NULL,
    hr_email VARCHAR(150) NOT NULL,
    hr_role VARCHAR(50) NOT NULL DEFAULT 'HR_MANAGER',
    checklist_photo INTEGER NOT NULL DEFAULT 1,
    checklist_name INTEGER NOT NULL DEFAULT 1,
    checklist_staff_no INTEGER NOT NULL DEFAULT 1,
    checklist_department INTEGER NOT NULL DEFAULT 1,
    checklist_designation INTEGER NOT NULL DEFAULT 1,
    checklist_layout INTEGER NOT NULL DEFAULT 1,
    approval_notes TEXT NULL,
    file_sha256_at_approval VARCHAR(64) NOT NULL,
    approved_at DATETIME NOT NULL,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id) REFERENCES id_versions(id) ON DELETE RESTRICT,
    FOREIGN KEY (hr_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS print_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NOT NULL,
    version_id INTEGER NOT NULL,
    printing_user_id INTEGER NOT NULL,
    printing_user_name VARCHAR(150) NOT NULL,
    file_sha256_at_print VARCHAR(64) NOT NULL,
    print_notes TEXT NULL,
    printed_at DATETIME NOT NULL,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id) REFERENCES id_versions(id) ON DELETE RESTRICT,
    FOREIGN KEY (printing_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS collection_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NOT NULL,
    hr_user_id INTEGER NOT NULL,
    collected_by_name VARCHAR(150) NOT NULL,
    collected_by_relationship VARCHAR(50) DEFAULT 'SELF',
    recipient_national_id_or_phone VARCHAR(100) NULL,
    collection_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    collected_at DATETIME NOT NULL,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (hr_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_corrections_card ON correction_requests(id_card_id);
CREATE INDEX IF NOT EXISTS idx_approval_card ON approval_records(id_card_id);
CREATE INDEX IF NOT EXISTS idx_approval_hr ON approval_records(hr_user_id);
CREATE INDEX IF NOT EXISTS idx_print_card ON print_records(id_card_id);
CREATE INDEX IF NOT EXISTS idx_collection_card ON collection_records(id_card_id);
