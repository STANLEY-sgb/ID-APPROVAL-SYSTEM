<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

use DateTime;
use DateTimeZone;

class Timezone
{
    private static ?DateTimeZone $appTimezone = null;

    /**
     * Configure PHP's default timezone to match app config.
     * Call once at application bootstrap.
     */
    public static function configure(): void
    {
        $tzName = (string)Config::get('APP_TIMEZONE', 'Africa/Kampala');
        date_default_timezone_set($tzName);
        self::$appTimezone = new DateTimeZone($tzName);
    }

    public static function getTimezone(): DateTimeZone
    {
        if (self::$appTimezone === null) {
            $tzName = (string)Config::get('APP_TIMEZONE', 'Africa/Kampala');
            self::$appTimezone = new DateTimeZone($tzName);
        }
        return self::$appTimezone;
    }

    public static function now(): DateTime
    {
        return new DateTime('now', self::getTimezone());
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function format(?string $datetimeStr, string $format = 'd M Y, H:i T'): string
    {
        if (empty($datetimeStr)) {
            return '—';
        }

        try {
            $dt = new DateTime($datetimeStr, self::getTimezone());
            return $dt->format($format);
        } catch (\Exception) {
            return $datetimeStr;
        }
    }

    public static function formatDetailed(?string $datetimeStr): string
    {
        if (empty($datetimeStr)) {
            return '—';
        }

        try {
            $dt = new DateTime($datetimeStr, self::getTimezone());
            return $dt->format('d F Y \a\t H:i:s \E\A\T');
        } catch (\Exception) {
            return $datetimeStr;
        }
    }

    public static function timeAgo(?string $datetimeStr): string
    {
        if (empty($datetimeStr)) {
            return '—';
        }

        try {
            $dt = new DateTime($datetimeStr, self::getTimezone());
            $now = self::now();
            $diff = $now->diff($dt);

            if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
            if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
            if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
            if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
            return 'just now';
        } catch (\Exception) {
            return $datetimeStr;
        }
    }

    public static function hoursDifference(?string $datetimeStr): float
    {
        if (empty($datetimeStr)) {
            return 0.0;
        }

        try {
            $dt = new DateTime($datetimeStr, self::getTimezone());
            $now = self::now();
            $diffSeconds = $now->getTimestamp() - $dt->getTimestamp();
            return round($diffSeconds / 3600, 1);
        } catch (\Exception) {
            return 0.0;
        }
    }
}
