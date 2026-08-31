# System Maintenance & Operations Manual

## 1. Routine Operational Procedures

### 1.1 Provisioning New Staff User Accounts
1. Log in as `ADMINISTRATOR` and navigate to `/admin/hr-accounts`.
2. Enter the staff member's Name, Official Email, Username, Payroll Staff ID, Department, and Role.
3. The system generates an active account with `force_password_change = 1`.
4. The user is required to set their permanent password immediately upon first login.

### 1.2 Managing User Passwords & Account Status
- **Password Reset**: In the Admin User table, click **"Reset Password"**. Enter a temporary password. The user will be prompted to change it on their next login.
- **Account Suspension**: Click **"Deactivate"** to suspend access immediately. The user's active sessions are terminated.

### 1.3 Executing Database Schema Migrations
When adding new schema modifications to `database/migrations/`:
```bash
php -r "require 'src/autoload.php'; \$m = new Mengo\IdApproval\Database\Migrator(); print_r(\$m->run());"
```

### 1.4 Rotating Application Secrets
1. Generate new cryptographic random keys.
2. Update `.env`.
3. Clear existing sessions by purging `storage/logs/` and restarting PHP-FPM:
   ```bash
   sudo systemctl restart php8.2-fpm
   ```
