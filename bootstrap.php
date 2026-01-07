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

require_once APPPATH . '/core/SessionManager.php';
require_once APPPATH . '/core/CSRF.php';
require_once APPPATH . '/core/AuditLogger.php';
require_once APPPATH . '/core/RBAC.php';
require_once APPPATH . '/core/Auth.php';
require_once APPPATH . '/core/TOTP.php';
require_once APPPATH . '/core/Storage.php';
require_once APPPATH . '/core/FileService.php';
require_once APPPATH . '/core/Messaging.php';
require_once APPPATH . '/core/SearchHelper.php';
require_once APPPATH . '/core/NotificationService.php';
require_once APPPATH . '/core/MessageService.php';

require_once APPPATH . '/models/User.php';
require_once APPPATH . '/models/Patient.php';
require_once APPPATH . '/models/Appointment.php';
require_once APPPATH . '/models/Billing.php';
require_once APPPATH . '/models/Payment.php';
require_once APPPATH . '/models/LaboratoryTest.php';
require_once APPPATH . '/models/VitalSigns.php';
require_once APPPATH . '/models/Consultation.php';
require_once APPPATH . '/models/Prescription.php';
require_once APPPATH . '/models/PrenatalBaseline.php';
require_once APPPATH . '/models/PrenatalVisit.php';

require_once APPPATH . '/core/RoleLayout.php';

require_once APPPATH . '/controllers/AuthController.php';
require_once APPPATH . '/controllers/RegisterController.php';
require_once APPPATH . '/controllers/PatientController.php';
require_once APPPATH . '/controllers/DoctorController.php';
require_once APPPATH . '/controllers/AdminController.php';
require_once APPPATH . '/controllers/AppointmentController.php';
require_once APPPATH . '/controllers/PaymentController.php';
require_once APPPATH . '/controllers/SecretaryController.php';
require_once APPPATH . '/controllers/ScheduleController.php';
require_once APPPATH . '/controllers/QueueController.php';
require_once APPPATH . '/controllers/LaboratoryController.php';

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
