<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Security;

use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class RateLimiter
{
    public static function isLocked(string $email, string $ip): bool
    {
        $pdo = Database::getConnection();
        $maxAttempts = (int)Config::get('RATE_LIMIT_LOGIN_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int)Config::get('RATE_LIMIT_LOGIN_LOCKOUT_MINUTES', 15);

        $cutoff = date('Y-m-d H:i:s', time() - ($lockoutMinutes * 60));

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE (email = ? OR ip_address = ?) 
              AND status = 'FAILED' 
              AND attempted_at >= ?
        ");
        $stmt->execute([$email, $ip, $cutoff]);
        $failures = (int)$stmt->fetchColumn();

        return $failures >= $maxAttempts;
    }

    public static function recordAttempt(string $email, string $ip, string $status): void
    {
        $pdo = Database::getConnection();
        $now = Timezone::nowString();

        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, status, attempted_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$email, $ip, $status, $now]);
    }

    public static function reset(string $email, string $ip): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM login_attempts 
            WHERE email = ? OR ip_address = ?
        ");
        $stmt->execute([$email, $ip]);
    }
}
