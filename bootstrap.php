<?php

declare(strict_types=1);

ob_start();

// Enable error reporting for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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

// Register the error handler
ErrorHandler::register();

// 2. Session Configuration & Initialization
SessionManager::start();

// Ensure storage directories exist for uploads/records
Storage::ensureDirs();

// updates session + DB last_activity (best-effort)
Auth::touchActivity();
