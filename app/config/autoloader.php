<?php

declare(strict_types=1);

spl_autoload_register(function ($className) {
    $classMap = [
        'User' => APP_PATH . '/models/User.php',
        'Patient' => APP_PATH . '/models/Patient.php',
        'Appointment' => APP_PATH . '/models/Appointment.php',
        'Billing' => APP_PATH . '/models/Billing.php',
        'Payment' => APP_PATH . '/models/Payment.php',
        'LaboratoryTest' => APP_PATH . '/models/LaboratoryTest.php',
        'VitalSigns' => APP_PATH . '/models/VitalSigns.php',
        'Consultation' => APP_PATH . '/models/Consultation.php',
        'Prescription' => APP_PATH . '/models/Prescription.php',
        'PrenatalBaseline' => APP_PATH . '/models/PrenatalBaseline.php',
        'PrenatalVisit' => APP_PATH . '/models/PrenatalVisit.php',

        'AuthController' => APP_PATH . '/controllers/AuthController.php',
        'RegisterController' => APP_PATH . '/controllers/RegisterController.php',
        'PatientController' => APP_PATH . '/controllers/PatientController.php',
        'DoctorController' => APP_PATH . '/controllers/DoctorController.php',
        'AdminController' => APP_PATH . '/controllers/AdminController.php',
        'AppointmentController' => APP_PATH . '/controllers/AppointmentController.php',
        'PaymentController' => APP_PATH . '/controllers/PaymentController.php',
        'SecretaryController' => APP_PATH . '/controllers/SecretaryController.php',
        'ScheduleController' => APP_PATH . '/controllers/ScheduleController.php',
        'QueueController' => APP_PATH . '/controllers/QueueController.php',
        'LaboratoryController' => APP_PATH . '/controllers/LaboratoryController.php',

        'SessionManager' => APP_PATH . '/core/SessionManager.php',
        'CSRF' => APP_PATH . '/core/CSRF.php',
        'AuditLogger' => APP_PATH . '/core/AuditLogger.php',
        'RBAC' => APP_PATH . '/core/RBAC.php',
        'Auth' => APP_PATH . '/core/Auth.php',
        'TOTP' => APP_PATH . '/core/TOTP.php',
        'Storage' => APP_PATH . '/core/Storage.php',
        'FileService' => APP_PATH . '/core/FileService.php',
        'Messaging' => APP_PATH . '/core/Messaging.php',
        'SearchHelper' => APP_PATH . '/core/SearchHelper.php',
        'NotificationService' => APP_PATH . '/core/NotificationService.php',
        'MessageService' => APP_PATH . '/core/MessageService.php',
        'RoleLayout' => APP_PATH . '/core/RoleLayout.php',
        'ErrorHandler' => APP_PATH . '/core/ErrorHandler.php',
        'Database' => APP_PATH . '/core/Database.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
