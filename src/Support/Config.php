<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $envFile = ''): void
    {
        if (self::$loaded) {
            return;
        }

        if (empty($envFile)) {
            $envFile = dirname(__DIR__, 2) . '/.env';
        }

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    self::$config[$key] = $value;
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        $val = self::$config[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }

        if (strtolower((string)$val) === 'true') return true;
        if (strtolower((string)$val) === 'false') return false;
        if (is_numeric($val)) {
            return str_contains((string)$val, '.') ? (float)$val : (int)$val;
        }

        return $val;
    }

    public static function isProduction(): bool
    {
        return strtolower((string)self::get('APP_ENV', 'development')) === 'production';
    }

    public static function isDebug(): bool
    {
        return (bool)self::get('APP_DEBUG', true) && !self::isProduction();
    }
}
