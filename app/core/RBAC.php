<?php
declare(strict_types=1);

final class RBAC
{
    public static function requireRole(string ...$roles): void
    {
        $u = Auth::user();
        if (!$u)
            redirect('/');

        if (!in_array($u['role'], $roles, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}
