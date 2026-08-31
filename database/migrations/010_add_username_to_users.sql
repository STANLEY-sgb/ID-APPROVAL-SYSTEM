-- 010: Add username column to users table for Username + Password Authentication
ALTER TABLE users ADD COLUMN username VARCHAR(100);

-- Populate username for existing standard seeded users
UPDATE users SET username = 'admin' WHERE email = 'admin@mengohospital.org';
UPDATE users SET username = 'designer' WHERE email = 'designer@mengohospital.org';
UPDATE users SET username = 'sarah.namukasa' WHERE email = 'sarah.namukasa@mengohospital.org';
UPDATE users SET username = 'david.kato' WHERE email = 'david.kato@mengohospital.org';
UPDATE users SET username = 'grace.nakato' WHERE email = 'grace.nakato@mengohospital.org';
UPDATE users SET username = 'peter.okello' WHERE email = 'printing@mengohospital.org';

-- Fallback for any other user: extract part before @ from email
UPDATE users SET username = LOWER(SUBSTR(email, 1, INSTR(email, '@') - 1)) WHERE username IS NULL OR username = '';

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username);
