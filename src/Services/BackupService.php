<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;
use RuntimeException;

class BackupService
{
    private string $backupDir;

    public function __construct(?string $backupDir = null)
    {
        $base = dirname(__DIR__, 2);
        $this->backupDir = $backupDir ?? $base . '/storage/backups';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
    }

    public function createBackup(): array
    {
        $pdo = Database::getConnection();

        // Checkpoint WAL to flush all transactions into main database file
        $pdo->exec("PRAGMA wal_checkpoint(TRUNCATE);");

        $dbRelative = (string)Config::get('DB_PATH', 'storage/database/app.sqlite');
        $base = dirname(__DIR__, 2);
        $dbPath = str_starts_with($dbRelative, '/') || preg_match('/^[A-Za-z]:/', $dbRelative)
            ? $dbRelative
            : $base . '/' . ltrim($dbRelative, '/\\');

        if (!file_exists($dbPath)) {
            throw new RuntimeException("Source database file not found: {$dbPath}");
        }

        $timestamp = date('Ymd_His');
        $backupFilename = "mengo_id_backup_{$timestamp}.sqlite";
        $backupFullPath = $this->backupDir . '/' . $backupFilename;

        if (!copy($dbPath, $backupFullPath)) {
            throw new RuntimeException("Failed to copy database file during backup creation.");
        }

        // Verify backup integrity
        $testPdo = new PDO('sqlite:' . $backupFullPath);
        $stmt = $testPdo->query("PRAGMA integrity_check;");
        $integrity = $stmt->fetchColumn();

        if ($integrity !== 'ok') {
            unlink($backupFullPath);
            throw new RuntimeException("Backup verification failed: Database integrity check returned '{$integrity}'");
        }

        $size = filesize($backupFullPath);
        $sha256 = hash_file('sha256', $backupFullPath);

        return [
            'filename' => $backupFilename,
            'full_path' => $backupFullPath,
            'size' => $size,
            'sha256' => $sha256,
            'integrity' => $integrity,
            'created_at' => Timezone::nowString()
        ];
    }

    public function listBackups(): array
    {
        $files = glob($this->backupDir . '/*.sqlite');
        $backups = [];

        foreach ($files as $f) {
            $backups[] = [
                'filename' => basename($f),
                'size' => filesize($f),
                'created_at' => date('Y-m-d H:i:s', filemtime($f)),
                'sha256' => hash_file('sha256', $f)
            ];
        }

        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $backups;
    }
}
