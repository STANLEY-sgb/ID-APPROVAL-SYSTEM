<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

/**
 * DateHelper — Database-agnostic date/time SQL fragment builder.
 *
 * Generates WHERE-clause fragments for time-based filtering that work
 * across SQLite, MySQL, and PostgreSQL by computing cutoff timestamps
 * in PHP rather than relying on database-specific functions.
 *
 * Usage:
 *   [$sql, $params] = DateHelper::olderThan('c.updated_at', 24);
 *   // $sql  = " AND c.updated_at <= ?"
 *   // $params = ['2026-08-29 10:00:00']
 */
class DateHelper
{
    /**
     * Returns a SQL fragment and bound parameter for "column is older than N hours".
     *
     * Example:
     *   [$sql, $params] = DateHelper::olderThan('c.updated_at', 24);
     *   $stmt = $pdo->prepare("SELECT * FROM id_cards WHERE 1=1 {$sql}");
     *   $stmt->execute($params);
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function olderThan(string $column, int $hours): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
        return [" AND {$column} <= ?", [$cutoff]];
    }

    /**
     * Returns a SQL fragment and bound parameter for "column is older than N days".
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function olderThanDays(string $column, int $days): array
    {
        return self::olderThan($column, $days * 24);
    }

    /**
     * Returns a SQL fragment and bound parameter for "column is within the last N hours".
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function withinHours(string $column, int $hours): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
        return [" AND {$column} >= ?", [$cutoff]];
    }

    /**
     * Returns a SQL fragment and bound parameter for "column is within the last N days".
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function withinDays(string $column, int $days): array
    {
        return self::withinHours($column, $days * 24);
    }

    /**
     * Compute a PHP ISO 8601 cutoff string for direct use in queries.
     * Useful when building SQL manually without fragment helpers.
     */
    public static function cutoff(int $hours): string
    {
        return date('Y-m-d H:i:s', time() - ($hours * 3600));
    }
}
