<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Database;

use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;
use RuntimeException;

class Migrator
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(?PDO $pdo = null, ?string $migrationsDir = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__, 2) . '/database/migrations';
    }

    public function run(): array
    {
        // 1. Ensure migrations table exists
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) UNIQUE NOT NULL,
                executed_at DATETIME NOT NULL
            );
        ");

        $executed = $this->pdo->query("SELECT migration FROM system_migrations")->fetchAll(PDO::FETCH_COLUMN);

        $migrationFiles = glob($this->migrationsDir . '/*.sql');
        sort($migrationFiles);

        // Temporarily disable foreign keys during schema migrations (e.g. table reconstruction)
        $this->pdo->exec("PRAGMA foreign_keys = OFF");

        foreach ($migrationFiles as $file) {
            $migrationName = basename($file);
            if (in_array($migrationName, $executed, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Cannot read migration file: {$file}");
            }

            // Strip UTF-8 BOM if present
            $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare("INSERT INTO system_migrations (migration, executed_at) VALUES (?, ?)");
                $stmt->execute([$migrationName, Timezone::nowString()]);
                $this->pdo->commit();
                $appliedNow[] = $migrationName;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->pdo->exec("PRAGMA foreign_keys = ON");
                throw new RuntimeException("Migration failed ({$migrationName}): " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        $this->pdo->exec("PRAGMA foreign_keys = ON");

        // Verify Database Integrity after migrations
        $integrity = Database::checkIntegrity();
        if ($integrity['status'] !== 'ok') {
            throw new RuntimeException("Database integrity check failed after migrations: " . json_encode($integrity));
        }

        return [
            'previously_executed' => count($executed),
            'applied_now' => $appliedNow,
            'integrity' => $integrity['status']
        ];
    }
}
