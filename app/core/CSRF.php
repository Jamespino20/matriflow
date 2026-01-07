<?php

declare(strict_types=1);

final class CSRF
{
    public static function token(): string
    {
        if (empty($_SESSION[CSRF_SESSION_KEY])) {
            $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[CSRF_SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (!$token) {
            error_log('CSRF Validation Failed: No token provided in request.');
            return false;
        }
        if (empty($_SESSION[CSRF_SESSION_KEY])) {
            error_log('CSRF Validation Failed: No token found in session. ID: ' . session_id());
            return false;
        }
        $match = hash_equals((string) $_SESSION[CSRF_SESSION_KEY], $token);
        if (!$match) {
            error_log('CSRF Validation Failed: Token mismatch. Session: ' . $_SESSION[CSRF_SESSION_KEY] . ' | Post: ' . $token);
        }
        return $match;
    }

    public static function input(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }
}
