-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jan 21, 2026 at 11:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `matriflow_db`
--
CREATE DATABASE IF NOT EXISTS `matriflow_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `matriflow_db`;

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor_user_id` int(11) DEFAULT NULL,
  `appointment_purpose` varchar(100) DEFAULT NULL,
  `appointment_status` enum('pending','scheduled','checked_in','in_consultation','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `service_type` varchar(50) DEFAULT 'general',
  `appointment_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `audit_log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `operation` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT') DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `changes_made` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `billing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hmo_provider_id` int(11) DEFAULT NULL,
  `hmo_claim_status` enum('none','submitted','approved','rejected','paid') DEFAULT 'none',
  `hmo_claim_reference` varchar(100) DEFAULT NULL,
  `hmo_claim_amount` decimal(10,2) DEFAULT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `billing_status` enum('unpaid','paid','partial','refunded','voided','refund_requested') NOT NULL DEFAULT 'unpaid',
  `due_date` date DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `item_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation`
--

CREATE TABLE `consultation` (
  `consultation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor_user_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `pregnancy_id` int(11) DEFAULT NULL,
  `consultation_type` varchar(50) DEFAULT 'General',
  `subjective_notes` text DEFAULT NULL,
  `objective_notes` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `uploader_user_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `category` enum('lab_result','prescription','identification','phr','other') DEFAULT 'other',
  `description` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmo_providers`
--

CREATE TABLE `hmo_providers` (
  `hmo_provider_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `short_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laboratory_test`
--

CREATE TABLE `laboratory_test` (
  `laboratory_test_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_result` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `result_file_path` varchar(255) DEFAULT NULL,
  `status` enum('ordered','completed','reviewed','released') NOT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `ordered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_body` mediumtext NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'Type: appointment_reminder, lab_result, payment_due, general, admin_broadcast',
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'Optional related record ID (appointment_id, billing_id, etc.)',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `password_reset_token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `requested_user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_queue`
--

CREATE TABLE `patient_queue` (
  `queue_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor_user_id` int(11) DEFAULT NULL,
  `position` int(11) NOT NULL,
  `status` enum('waiting','checked_in','in_consultation','finished','skipped','cancelled','no_show') NOT NULL DEFAULT 'waiting',
  `checked_in_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancies`
--

CREATE TABLE `pregnancies` (
  `pregnancy_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gravida` int(11) DEFAULT 0,
  `para` int(11) DEFAULT 0,
  `abortions` int(11) DEFAULT 0,
  `living_children` int(11) DEFAULT 0,
  `lmp_date` date NOT NULL,
  `estimated_due_date` date NOT NULL,
  `status` enum('active','completed','miscarriage','abortion') DEFAULT 'active',
  `delivery_date` date DEFAULT NULL,
  `delivery_mode` enum('SVD','CS','VBAC','assisted') DEFAULT NULL,
  `next_visit_due` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prenatal_observations`
--

CREATE TABLE `prenatal_observations` (
  `observation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pregnancy_id` int(11) NOT NULL,
  `fundal_height_cm` decimal(5,2) DEFAULT NULL,
  `fetal_heart_rate` int(11) DEFAULT NULL,
  `fetal_movement_noted` tinyint(1) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription`
--

CREATE TABLE `prescription` (
  `prescription_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor_user_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `prescribed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promissory_notes`
--

CREATE TABLE `promissory_notes` (
  `promissory_note_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `promised_amount` decimal(10,2) NOT NULL,
  `promise_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','defaulted') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminder_logs`
--

CREATE TABLE `reminder_logs` (
  `reminder_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `type` enum('sms','email','call') NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `status` enum('sent','failed','queued') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_schedule`
--

CREATE TABLE `staff_schedule` (
  `schedule_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `comments` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','doctor','secretary','patient') NOT NULL,
  `registration_type` enum('Patient','Guardian') DEFAULT 'Patient',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `account_status` enum('pending','active','locked','suspended') DEFAULT 'pending',
  `identification_number` varchar(20) DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed','Separated') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `google_2fa_secret` varchar(64) DEFAULT NULL,
  `backup_tokens` text DEFAULT NULL,
  `is_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `force_2fa_setup` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `vital_signs_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `medical_history` text NOT NULL,
  `allergies` text NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `systolic_pressure` int(11) DEFAULT NULL,
  `diastolic_pressure` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `temperature_celsius` decimal(4,2) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_patients`
-- (See below for the actual view)
--
CREATE TABLE `v_active_patients` (
`user_id` int(11)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(100)
,`contact_number` varchar(20)
,`identification_number` varchar(20)
,`dob` date
,`address` text
,`created_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_todays_appointments`
-- (See below for the actual view)
--
CREATE TABLE `v_todays_appointments` (
`appointment_id` int(11)
,`appointment_date` datetime
,`appointment_status` enum('pending','scheduled','checked_in','in_consultation','completed','cancelled','no_show')
,`appointment_purpose` varchar(100)
,`patient_id` int(11)
,`patient_first_name` varchar(100)
,`patient_last_name` varchar(100)
,`doctor_user_id` int(11)
,`doctor_first_name` varchar(100)
,`doctor_last_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `v_active_patients`
--
DROP TABLE IF EXISTS `v_active_patients`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_patients`  AS SELECT `users`.`user_id` AS `user_id`, `users`.`first_name` AS `first_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`contact_number` AS `contact_number`, `users`.`identification_number` AS `identification_number`, `users`.`dob` AS `dob`, `users`.`address` AS `address`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`role` = 'patient' AND `users`.`is_active` = 1 AND `users`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_todays_appointments`
--
DROP TABLE IF EXISTS `v_todays_appointments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_todays_appointments`  AS SELECT `a`.`appointment_id` AS `appointment_id`, `a`.`appointment_date` AS `appointment_date`, `a`.`appointment_status` AS `appointment_status`, `a`.`appointment_purpose` AS `appointment_purpose`, `a`.`user_id` AS `patient_id`, `pu`.`first_name` AS `patient_first_name`, `pu`.`last_name` AS `patient_last_name`, `a`.`doctor_user_id` AS `doctor_user_id`, `du`.`first_name` AS `doctor_first_name`, `du`.`last_name` AS `doctor_last_name` FROM ((`appointment` `a` join `users` `pu` on(`a`.`user_id` = `pu`.`user_id`)) left join `users` `du` on(`a`.`doctor_user_id` = `du`.`user_id`)) WHERE cast(`a`.`appointment_date` as date) = curdate() AND `a`.`deleted_at` is null ORDER BY `a`.`appointment_date` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_date_status` (`appointment_date`,`appointment_status`),
  ADD KEY `idx_doctor_date` (`doctor_user_id`,`appointment_date`),
  ADD KEY `idx_date_range` (`appointment_date`,`deleted_at`),
  ADD KEY `appointment_fk_user` (`user_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`audit_log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `idx_user_status` (`user_id`,`billing_status`),
  ADD KEY `idx_unpaid` (`billing_status`,`created_at`),
  ADD KEY `recorded_by_user_id` (`recorded_by_user_id`),
  ADD KEY `fk_billing_hmo` (`hmo_provider_id`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `billing_id` (`billing_id`);

--
-- Indexes for table `consultation`
--
ALTER TABLE `consultation`
  ADD PRIMARY KEY (`consultation_id`),
  ADD KEY `fk_cons_patient` (`user_id`),
  ADD KEY `fk_cons_doctor` (`doctor_user_id`),
  ADD KEY `consultation_fk_pregnancy` (`pregnancy_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_uploader` (`uploader_user_id`),
  ADD KEY `idx_patient` (`user_id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `hmo_providers`
--
ALTER TABLE `hmo_providers`
  ADD PRIMARY KEY (`hmo_provider_id`),
  ADD UNIQUE KEY `short_code` (`short_code`);

--
-- Indexes for table `laboratory_test`
--
ALTER TABLE `laboratory_test`
  ADD PRIMARY KEY (`laboratory_test_id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `idx_patient_status` (`user_id`,`status`),
  ADD KEY `idx_ordered` (`ordered_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_message_id` (`message_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`password_reset_token_id`),
  ADD UNIQUE KEY `uniq_token_hash` (`token_hash`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `patient_queue`
--
ALTER TABLE `patient_queue`
  ADD PRIMARY KEY (`queue_id`),
  ADD KEY `fk_queue_appointment` (`appointment_id`),
  ADD KEY `fk_queue_patient` (`user_id`),
  ADD KEY `fk_queue_doctor` (`doctor_user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `billing_id` (`billing_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD PRIMARY KEY (`pregnancy_id`),
  ADD UNIQUE KEY `unique_active_pregnancy` (`user_id`,`status`),
  ADD KEY `idx_edd` (`estimated_due_date`);

--
-- Indexes for table `prenatal_observations`
--
ALTER TABLE `prenatal_observations`
  ADD PRIMARY KEY (`observation_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pregnancy_id` (`pregnancy_id`);

--
-- Indexes for table `prescription`
--
ALTER TABLE `prescription`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `fk_presc_patient` (`user_id`),
  ADD KEY `fk_presc_doctor` (`doctor_user_id`);

--
-- Indexes for table `promissory_notes`
--
ALTER TABLE `promissory_notes`
  ADD PRIMARY KEY (`promissory_note_id`),
  ADD KEY `fk_pn_billing` (`billing_id`),
  ADD KEY `fk_pn_user` (`user_id`);

--
-- Indexes for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `fk_rem_app` (`appointment_id`),
  ADD KEY `idx_appointment` (`appointment_id`),
  ADD KEY `idx_type_status` (`type`,`status`);

--
-- Indexes for table `staff_schedule`
--
ALTER TABLE `staff_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `fk_schedule_doctor` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `identification_number` (`identification_number`),
  ADD KEY `idx_role_active` (`role`,`is_active`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`vital_signs_id`),
  ADD KEY `idx_patient_time` (`user_id`,`recorded_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `audit_log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultation`
--
ALTER TABLE `consultation`
  MODIFY `consultation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hmo_providers`
--
ALTER TABLE `hmo_providers`
  MODIFY `hmo_provider_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laboratory_test`
--
ALTER TABLE `laboratory_test`
  MODIFY `laboratory_test_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `password_reset_token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_queue`
--
ALTER TABLE `patient_queue`
  MODIFY `queue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pregnancies`
--
ALTER TABLE `pregnancies`
  MODIFY `pregnancy_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prenatal_observations`
--
ALTER TABLE `prenatal_observations`
  MODIFY `observation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription`
--
ALTER TABLE `prescription`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promissory_notes`
--
ALTER TABLE `promissory_notes`
  MODIFY `promissory_note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_schedule`
--
ALTER TABLE `staff_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `vital_signs_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `appointment_fk_doctor` FOREIGN KEY (`doctor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointment_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_log_fk_user_hotfix` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_billing_hmo` FOREIGN KEY (`hmo_provider_id`) REFERENCES `hmo_providers` (`hmo_provider_id`) ON DELETE SET NULL;

--
-- Constraints for table `consultation`
--
ALTER TABLE `consultation`
  ADD CONSTRAINT `consultation_fk_doctor` FOREIGN KEY (`doctor_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_fk_pregnancy` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`pregnancy_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultation_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_fk_uploader` FOREIGN KEY (`uploader_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `laboratory_test`
--
ALTER TABLE `laboratory_test`
  ADD CONSTRAINT `lab_test_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `laboratory_test_ibfk_2` FOREIGN KEY (`consultation_id`) REFERENCES `consultation` (`consultation_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_fk_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_fk_receiver_hotfix` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_fk_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_fk_sender_hotfix` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `prt_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_queue`
--
ALTER TABLE `patient_queue`
  ADD CONSTRAINT `fk_queue_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_fk_doctor` FOREIGN KEY (`doctor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `queue_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`billing_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD CONSTRAINT `pregnancies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `prenatal_observations`
--
ALTER TABLE `prenatal_observations`
  ADD CONSTRAINT `prenatal_observations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prenatal_observations_ibfk_2` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`pregnancy_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription`
--
ALTER TABLE `prescription`
  ADD CONSTRAINT `prescription_fk_doctor` FOREIGN KEY (`doctor_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `promissory_notes`
--
ALTER TABLE `promissory_notes`
  ADD CONSTRAINT `fk_pn_billing` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`billing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  ADD CONSTRAINT `fk_rem_app` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_schedule`
--
ALTER TABLE `staff_schedule`
  ADD CONSTRAINT `schedule_fk_doctor` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `sessions_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_fk_user_hotfix` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
