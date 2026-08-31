-- 009: Allow ADMINISTRATOR role in users table CHECK constraint
CREATE TABLE users_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL CHECK(role IN ('DESIGNER', 'HR_MANAGER', 'PRINTING_OFFICER', 'ADMINISTRATOR')),
    department VARCHAR(100) DEFAULT 'Administration',
    phone VARCHAR(50) NULL,
    status VARCHAR(20) DEFAULT 'ACTIVE' CHECK(status IN ('ACTIVE', 'INACTIVE', 'SUSPENDED')),
    force_password_change INTEGER DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

INSERT INTO users_new (
    id, staff_id, name, email, password_hash, role, department, phone, status, force_password_change, last_login_at, created_at, updated_at
)
SELECT id, staff_id, name, email, password_hash, role, department, phone, status, force_password_change, last_login_at, created_at, updated_at
FROM users;

DROP TABLE users;

ALTER TABLE users_new RENAME TO users;

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
