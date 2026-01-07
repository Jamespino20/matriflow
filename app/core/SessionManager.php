<?php

declare(strict_types=1);

final class SessionManager
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            return;

        // Ensure session config matches bootstrap expectations
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_name(SESSION_NAME);

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 7200, // 2 hours
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // absolute expiry
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        } elseif (time() - (int) $_SESSION['_created_at'] > SESSION_ABSOLUTE_SECONDS) {
            self::destroy();
            return;
        }

        // idle expiry
        if (isset($_SESSION['_last_seen']) && (time() - (int) $_SESSION['_last_seen'] > SESSION_IDLE_SECONDS)) {
            self::destroy();
            return;
        }
        $_SESSION['_last_seen'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            return;
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            return;

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
