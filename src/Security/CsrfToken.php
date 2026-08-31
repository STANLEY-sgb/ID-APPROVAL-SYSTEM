<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Security;

use Mengo\IdApproval\Support\Config;

class CsrfToken
{
    private const SESSION_KEY = '_csrf_token';
    private const SESSION_TIME_KEY = '_csrf_time';

    public static function generate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SessionManager::start();
        }

        $expiry = (int)Config::get('CSRF_EXPIRY', 3600);
        $time = time();

        if (
            empty($_SESSION[self::SESSION_KEY]) || 
            empty($_SESSION[self::SESSION_TIME_KEY]) || 
            ($time - (int)$_SESSION[self::SESSION_TIME_KEY]) > $expiry
        ) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_TIME_KEY] = $time;
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function get(): string
    {
        return self::generate();
    }

    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SessionManager::start();
        }

        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $expiry = (int)Config::get('CSRF_EXPIRY', 3600);
        if ((time() - (int)($_SESSION[self::SESSION_TIME_KEY] ?? 0)) > $expiry) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY], (string)$token);
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::get(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}
