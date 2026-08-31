# Environment Configuration Reference

The application uses an environment-driven configuration model loaded via `src/Support/Config.php`. All runtime parameters are read from the `.env` file at the application root.

---

## 1. Complete `.env` Specification

```ini
# ==============================================================================
# MENGO HOSPITAL ID MANAGEMENT SYSTEM — ENVIRONMENT CONFIGURATION
# ==============================================================================

# ------------------------------------------------------------------------------
# 1. APPLICATION CORE
# ------------------------------------------------------------------------------
# Name displayed in page headers, notifications, and reports
APP_NAME="Mengo Hospital ID Management System"

# Environment mode: 'development', 'staging', or 'production'
# In production, quick-login is disabled, errors are suppressed, and logs are secured.
APP_ENV=production

# Detailed debug output (true / false). MUST be false in production.
APP_DEBUG=false

# Canonical application URL used in notification links and redirects
APP_URL=https://id.mengohospital.org

# Application timezone (must be valid IANA timezone)
APP_TIMEZONE=Africa/Kampala

# ------------------------------------------------------------------------------
# 2. DATABASE CONFIGURATION (SQLite)
# ------------------------------------------------------------------------------
# Database driver
DB_DRIVER=sqlite

# Relative or absolute path to the SQLite database file
DB_PATH=storage/database/app.sqlite

# SQLite busy timeout in milliseconds (prevents database locking collisions)
DB_TIMEOUT=5000

# ------------------------------------------------------------------------------
# 3. FILE UPLOAD & STORAGE CONFIGURATION
# ------------------------------------------------------------------------------
# Maximum allowed file upload size in bytes (31,457,280 bytes = 30 MB)
MAX_UPLOAD_SIZE=31457280

# Comma-separated list of allowed MIME types
ALLOWED_MIME_TYPES=application/pdf

# Directory for permanent storage of verified ID card PDFs (isolated from web root)
STORAGE_PROTECTED_PATH=storage/uploads/protected

# ------------------------------------------------------------------------------
# 4. SECURITY & SESSION MANAGEMENT
# ------------------------------------------------------------------------------
# Session lifetime in seconds (7200 seconds = 2 hours)
SESSION_LIFETIME=7200

# CSRF token expiration window in seconds (3600 seconds = 1 hour)
CSRF_EXPIRY=3600

# Maximum consecutive failed login attempts before lockout
RATE_LIMIT_LOGIN_MAX_ATTEMPTS=5

# Lockout window duration in minutes after exceeding max failed attempts
RATE_LIMIT_LOGIN_LOCKOUT_MINUTES=15

# ------------------------------------------------------------------------------
# 5. INITIAL SEEDING PASSWORDS (FIRST BOOTSTRAP ONLY)
# ------------------------------------------------------------------------------
# Used ONLY during initial system seeding. Force password change is set to 1.
# Change these values before deploying to production.
INITIAL_ADMIN_PASSWORD=ChangeMe_Admin!2026
INITIAL_DESIGNER_PASSWORD=ChangeMe_Designer!2026
INITIAL_HR_PASSWORD=ChangeMe_HR!2026
INITIAL_PRINTING_PASSWORD=ChangeMe_Print!2026

# ------------------------------------------------------------------------------
# 6. MAIL & SMTP CONFIGURATION (OPTIONAL)
# ------------------------------------------------------------------------------
# Database in-app notifications are the primary channel. Set to true to enable email.
MAIL_ENABLED=false

# SMTP Server hostname
MAIL_HOST=smtp.mailtrap.io

# SMTP Port (25, 465, 587, 2525)
MAIL_PORT=2525

# SMTP Authentication credentials
MAIL_USERNAME=
MAIL_PASSWORD=

# Outgoing sender details
MAIL_FROM_ADDRESS=notifications@mengohospital.org
MAIL_FROM_NAME="Mengo Hospital ID System"
```

---

## 2. Configuration Access in Code

Configuration parameters are retrieved statically via `Mengo\IdApproval\Support\Config`:

```php
use Mengo\IdApproval\Support\Config;

// Get value with optional fallback default
$appName = Config::get('APP_NAME', 'Mengo Hospital ID System');

// Helper methods
$isProd = Config::isProduction(); // bool
$isDebug = Config::isDebug();     // bool
```
