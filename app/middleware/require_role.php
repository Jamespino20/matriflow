<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';

$roles = $roles ?? [];
RBAC::requireRole(...$roles);
