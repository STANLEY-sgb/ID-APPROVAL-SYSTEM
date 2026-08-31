-- 003: Create ID Cards and Versions
CREATE TABLE IF NOT EXISTS id_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    card_reference VARCHAR(60) UNIQUE NOT NULL,
    employee_id INTEGER NOT NULL,
    current_status VARCHAR(40) NOT NULL CHECK(current_status IN (
        'DRAFT', 
        'UPLOADED', 
        'PENDING_HR_APPROVAL', 
        'CORRECTION_REQUESTED', 
        'APPROVED', 
        'PRINTED', 
        'COLLECTED',
        'IMPORT_REVIEW_REQUIRED'
    )),
    current_version_number INTEGER DEFAULT 1,
    approved_version_id INTEGER NULL,
    created_by_user_id INTEGER NOT NULL,
    assigned_designer_id INTEGER NULL,
    needs_import_review INTEGER DEFAULT 0,
    import_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_designer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_version_id) REFERENCES id_versions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS id_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_card_id INTEGER NOT NULL,
    version_number INTEGER NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    file_sha256 VARCHAR(64) NOT NULL,
    mime_type VARCHAR(50) DEFAULT 'application/pdf',
    uploaded_by_user_id INTEGER NOT NULL,
    correction_request_id INTEGER NULL,
    is_approved INTEGER DEFAULT 0,
    uploaded_at DATETIME NOT NULL,
    FOREIGN KEY (id_card_id) REFERENCES id_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE(id_card_id, version_number)
);

CREATE INDEX IF NOT EXISTS idx_id_cards_status ON id_cards(current_status);
CREATE INDEX IF NOT EXISTS idx_id_cards_employee ON id_cards(employee_id);
CREATE INDEX IF NOT EXISTS idx_id_cards_card_ref ON id_cards(card_reference);
CREATE INDEX IF NOT EXISTS idx_id_cards_created_at ON id_cards(created_at);
CREATE INDEX IF NOT EXISTS idx_id_versions_card ON id_versions(id_card_id);
CREATE INDEX IF NOT EXISTS idx_id_versions_sha256 ON id_versions(file_sha256);
