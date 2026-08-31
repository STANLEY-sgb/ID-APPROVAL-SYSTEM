# Backup & Recovery Architecture

## 1. Hot Backup Mechanics (SQLite Online Backup API)

The application implements online, zero-downtime backups via `Mengo\IdApproval\Services\BackupService`. It leverages the native **SQLite Online Backup API** (`PDO::sqliteBackup`), which creates a transactionally consistent copy of the database while active read and write operations continue without blocking.

---

## 2. Backup Execution

### 2.1 Web UI (HR Managers & Administrators)
Navigate to `/backups` and click **"Create Database Backup"**.
- Generates a timestamped file: `storage/backups/mengo_id_backup_YYYYMMDD_HHMMSS.sqlite`.
- Calculates SHA-256 checksum and logs the event to the audit trail (`DATABASE_BACKUP`).
- Provides a direct administrative download link.

### 2.2 Automated Cron Job (CLI)
Configure a daily cron job at midnight to back up both the database and the PDF archive:

```bash
# Edit crontab
crontab -e

# Daily midnight hot backup + PDF sync
0 0 * * * /usr/bin/php /var/www/mengo-id-system/scripts/backup.php >> /var/log/mengo_backup.log 2>&1
```

---

## 3. Restoration Procedure

In the event of database corruption or hardware failure:

1. **Stop Web Server**:
   ```bash
   sudo systemctl stop nginx php8.2-fpm
   ```
2. **Preserve Corrupted State for Forensics**:
   ```bash
   mv storage/database/app.sqlite storage/database/app_corrupted_$(date +%s).sqlite
   ```
3. **Restore Backup Snapshot**:
   ```bash
   cp storage/backups/mengo_id_backup_20260830_003808.sqlite storage/database/app.sqlite
   sudo chown www-data:www-data storage/database/app.sqlite
   sudo chmod 664 storage/database/app.sqlite
   ```
4. **Restart Web Server & Run Verification**:
   ```bash
   sudo systemctl start nginx php8.2-fpm
   php tests/run_all_tests.php
   ```
