<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Security;

use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Support\Config;

class SessionManager
{
    private const USER_SESSION_KEY = '_auth_user';
    private const FLASH_SESSION_KEY = '_flash_messages';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent() || php_sapi_name() === 'cli') {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            return;
        }

        $lifetime = (int)Config::get('SESSION_LIFETIME', 7200);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function setUser(User $user): void
    {
        self::start();
        self::regenerate();
        $_SESSION[self::USER_SESSION_KEY] = [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'force_password_change' => $user->force_password_change,
            'authenticated_at' => time()
        ];
    }

    public static function getUser(): ?array
    {
        self::start();
        return $_SESSION[self::USER_SESSION_KEY] ?? null;
    }

    public static function getUserId(): ?int
    {
        $user = self::getUser();
        return $user ? (int)$user['id'] : null;
    }

    public static function getUserRole(): ?string
    {
        $user = self::getUser();
        return $user ? (string)$user['role'] : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::getUser() !== null;
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        if (!isset($_SESSION[self::FLASH_SESSION_KEY])) {
            $_SESSION[self::FLASH_SESSION_KEY] = [];
        }
        $_SESSION[self::FLASH_SESSION_KEY][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION[self::FLASH_SESSION_KEY] ?? [];
        unset($_SESSION[self::FLASH_SESSION_KEY]);
        return $flashes;
    }
}
