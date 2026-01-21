<?php

declare(strict_types=1);

spl_autoload_register(function ($className) {
    $classMap = [

        // =====================
        // Models
        // =====================
        'Appointment'          => APP_PATH . '/models/Appointment.php',
        'Billing'              => APP_PATH . '/models/Billing.php',
        'Consultation'         => APP_PATH . '/models/Consultation.php',
        'HmoProvider'          => APP_PATH . '/models/HmoProvider.php',
        'LaboratoryTest'       => APP_PATH . '/models/LaboratoryTest.php',
        'Patient'              => APP_PATH . '/models/Patient.php',
        'Payment'              => APP_PATH . '/models/Payment.php',
        'Pregnancy'            => APP_PATH . '/models/Pregnancy.php',
        'PrenatalObservation'  => APP_PATH . '/models/PrenatalObservation.php',
        'Prescription'         => APP_PATH . '/models/Prescription.php',
        'User'                 => APP_PATH . '/models/User.php',
        'VitalSigns'           => APP_PATH . '/models/VitalSigns.php',

        // =====================
        // Controllers
        // =====================
        'AdminController'        => APP_PATH . '/controllers/AdminController.php',
        'AppointmentController'  => APP_PATH . '/controllers/AppointmentController.php',
        'AuthController'         => APP_PATH . '/controllers/AuthController.php',
        'ConsultationController' => APP_PATH . '/controllers/ConsultationController.php',
        'DoctorController'       => APP_PATH . '/controllers/DoctorController.php',
        'LaboratoryController'   => APP_PATH . '/controllers/LaboratoryController.php',
        'PatientController'      => APP_PATH . '/controllers/PatientController.php',
        'PaymentController'      => APP_PATH . '/controllers/PaymentController.php',
        'QueueController'        => APP_PATH . '/controllers/QueueController.php',
        'RegisterController'     => APP_PATH . '/controllers/RegisterController.php',
        'ScheduleController'     => APP_PATH . '/controllers/ScheduleController.php',
        'SecretaryController'    => APP_PATH . '/controllers/SecretaryController.php',

        // =====================
        // Core
        // =====================
        'AuditLogger'         => APP_PATH . '/core/AuditLogger.php',
        'Auth'                => APP_PATH . '/core/Auth.php',
        'CSRF'                => APP_PATH . '/core/CSRF.php',
        'Database'            => APP_PATH . '/core/Database.php',
        'ErrorHandler'        => APP_PATH . '/core/ErrorHandler.php',
        'FileService'         => APP_PATH . '/core/FileService.php',
        'Mailer'              => APP_PATH . '/core/Mailer.php',
        'MessageService'      => APP_PATH . '/core/MessageService.php',
        'Messaging'           => APP_PATH . '/core/Messaging.php',
        'PregnancyCalculator' => APP_PATH . '/core/PregnancyCalculator.php',
        'RBAC'                => APP_PATH . '/core/RBAC.php',
        'RoleLayout'          => APP_PATH . '/core/RoleLayout.php',
        'SearchHelper'        => APP_PATH . '/core/SearchHelper.php',
        'SessionManager'      => APP_PATH . '/core/SessionManager.php',
        'Storage'             => APP_PATH . '/core/Storage.php',
        'TOTP'                => APP_PATH . '/core/TOTP.php',

        // =====================
        // Services
        // =====================
        'AbuseDetectionService' => APP_PATH . '/services/AbuseDetectionService.php',
        'NotificationService'   => APP_PATH . '/services/NotificationService.php',
        'PricingService'        => APP_PATH . '/services/PricingService.php',
        'ReminderService'       => APP_PATH . '/services/ReminderService.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
