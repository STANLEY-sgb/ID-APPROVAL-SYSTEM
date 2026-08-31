<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static ?string $customDbPath = null;

    public static function setCustomPath(?string $path): void
    {
        self::$customDbPath = $path;
        self::$instance = null;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dbRelative = self::$customDbPath ?? (string)Config::get('DB_PATH', 'storage/database/app.sqlite');
        $dbPath = str_starts_with($dbRelative, '/') || preg_match('/^[A-Za-z]:/', $dbRelative)
            ? $dbRelative
            : dirname(__DIR__, 2) . '/' . ltrim($dbRelative, '/\\');

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        try {
            $dsn = 'sqlite:' . $dbPath;
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $timeout = (int)Config::get('DB_TIMEOUT', 5000);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = {$timeout};");
            $pdo->exec("PRAGMA synchronous = NORMAL;");

            self::$instance = $pdo;
            return self::$instance;
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function checkIntegrity(): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->query("PRAGMA integrity_check;");
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $fkStmt = $pdo->query("PRAGMA foreign_key_check;");
        $fkViolations = $fkStmt->fetchAll();

        return [
            'status' => (count($results) === 1 && $results[0] === 'ok' && empty($fkViolations)) ? 'ok' : 'error',
            'integrity' => $results,
            'fk_violations' => $fkViolations
        ];
    }
}
