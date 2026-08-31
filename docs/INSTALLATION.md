# Installation & Setup Guide

## 1. System Requirements

| Component | Minimum Specification | Recommended Specification |
| :--- | :--- | :--- |
| **Operating System** | Linux (Ubuntu 22.04 LTS / Debian 12 / RHEL 9) or Windows Server | Ubuntu 24.04 LTS |
| **PHP Engine** | PHP 8.0.0+ | PHP 8.2 or 8.3 |
| **PHP Extensions** | `pdo`, `pdo_sqlite`, `fileinfo`, `mbstring`, `openssl`, `json`, `ctype` | All standard extensions enabled |
| **Web Server** | Apache 2.4+ (with `mod_rewrite`) or Nginx 1.20+ | Nginx + PHP-FPM |
| **Memory** | 512 MB RAM | 2 GB+ RAM (for large PDF batch merging) |
| **Disk Storage** | 1 GB free space | 20 GB+ NVMe SSD (scaled according to PDF archive volume) |

---

## 2. Directory Structure Verification

Ensure the application root directory contains the following layout:

```text
mengo-id-system/
├── database/
│   ├── migrations/          # Version-controlled SQL migration scripts (001 - 010)
├── docs/                    # Complete institutional documentation
├── public/                  # Document root (only this folder is web-accessible)
│   ├── assets/              # CSS, JavaScript, icons, hospital logos
│   ├── .htaccess            # Apache rewrite rules
│   └── index.php            # Application bootstrap & entry point
├── src/                     # Core application source code
│   ├── Controllers/
│   ├── Database/
│   ├── Middleware/
│   ├── Models/
│   ├── Repositories/
│   ├── Security/
│   ├── Services/
│   ├── Support/
│   ├── Views/
│   └── autoload.php         # PSR-4 pure-PHP autoloader
├── storage/                 # Data, uploads, logs, and backups (PRIVATE)
│   ├── backups/
│   ├── database/
│   ├── logs/
│   ├── temp/
│   └── uploads/
│       └── protected/
├── tests/                   # Automated regression and workflow test suites
├── .env.example             # Configuration template
├── .gitignore               # Production safety exclusion rules
└── README.md
```

---

## 3. Step-by-Step Installation

### Step 3.1: Clone or Place the Application
```bash
cd /var/www
git clone <repository_url> mengo-id-system
cd mengo-id-system
```

### Step 3.2: Configure Environment Variables
Copy the environment template:
```bash
cp .env.example .env
```
Edit `.env` and set your deployment parameters:
```ini
APP_NAME="Mengo Hospital ID Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://id.mengohospital.org
APP_TIMEZONE=Africa/Kampala

# SQLite Database Settings
DB_DRIVER=sqlite
DB_PATH=storage/database/app.sqlite
DB_TIMEOUT=5000

# File Upload Settings
MAX_UPLOAD_SIZE=31457280
ALLOWED_MIME_TYPES=application/pdf
STORAGE_PROTECTED_PATH=storage/uploads/protected

# Initial Administrative & Staff Passwords
INITIAL_ADMIN_PASSWORD=SetAStrongPassword2026!
INITIAL_DESIGNER_PASSWORD=SetAStrongPassword2026!
INITIAL_HR_PASSWORD=SetAStrongPassword2026!
INITIAL_PRINTING_PASSWORD=SetAStrongPassword2026!
```

### Step 3.3: Set Up Directory Permissions (Linux/Unix)
Ensure that the web server user (`www-data` or `nginx`) owns the storage directory:
```bash
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/
```

### Step 3.4: Run Database Migrations
Execute the automated database migrator to initialize the database and tables:
```bash
php -r "require 'src/autoload.php'; \$m = new Mengo\IdApproval\Database\Migrator(); print_r(\$m->run());"
```

### Step 3.5: Run Automated Verification Suite
Verify that all 74 core integration tests pass on your environment:
```bash
php tests/run_all_tests.php
```
Expected output:
```text
=======================================================
TEST SUITE SUMMARY: 74 Passed, 0 Failed
ALL TESTS PASSED WITH 100% SUCCESS!
=======================================================
```
