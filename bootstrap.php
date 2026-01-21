<?php

declare(strict_types=1);

ob_start();

// Enable error reporting for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Check required extensions
$requiredExtensions = ['pdo_mysql', 'openssl', 'mbstring', 'json'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    // Only log error, don't die, in case CLI config differs from Web config or if we want to attempt anyway
    error_log("Warning: The following PHP extensions are missing in this environment: " . implode(', ', $missingExtensions));
}

date_default_timezone_set('Asia/Manila');

define('BASE_PATH', __DIR__);
define('FCPATH', BASE_PATH);
define('APP_PATH', BASE_PATH . '/app');
define('APPPATH', APP_PATH);
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APPPATH . '/config/constants.php';
require_once APPPATH . '/config/security.php';
require_once APPPATH . '/config/database.php';
require_once APPPATH . '/config/autoloader.php';

try {
    // 2. Session Configuration & Initialization
    SessionManager::start();
} catch (Throwable $e) {
    error_log('bootstrap.php: SessionManager::start() failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
}

// Ensure storage directories exist for uploads/records
try {
    Storage::ensureDirs();
} catch (Throwable $e) {
    error_log('bootstrap.php: Storage::ensureDirs() failed: ' . $e->getMessage());
}

try {
    Auth::touchActivity(); // updates session + DB last_activity (best-effort)
} catch (Throwable $e) {
    error_log('bootstrap.php: Auth::touchActivity() failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
}

try {
    ReminderService::processDailyReminders();
} catch (Throwable $e) {
    error_log('bootstrap.php: ReminderService::processDailyReminders() failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
}
