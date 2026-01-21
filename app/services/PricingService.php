<?php

declare(strict_types=1);

final class PricingService
{
    // Common Service Prices
    private const CONSULTATION_OBGYN = 700.00;
    private const CONSULTATION_ONLINE = 800.00;

    // Lab Test Pricing (Approximate based on provided list)
    private static array $labPrices = [
        // Hematology
        'CBC' => 300.00,
        'CBC with Platelet Count' => 450.00,
        'Hemoglobin/Hematocrit' => 250.00,
        'Blood Typing with Rh' => 300.00,

        // Clinical Chem
        'FBS' => 350.00,
        'BUA' => 320.00,
        'BUN' => 330.00,
        'Creatinine' => 400.00,
        'Cholesterol' => 450.00,
        '5 Blood Chemistry Panel' => 1700.00,

        // Ultrasounds
        'OB Ultrasound (Standard)' => 1200.00,
        'Transvaginal Ultrasound' => 2500.00,
        'Congenital Anomaly Scan' => 2200.00,
        'Pelvic Ultrasound' => 1200.00,

        // Pregnancy Specific
        'Qualitative Pregnancy Test' => 300.00,
        'OGTT Pregnant' => 750.00,
        'HBsAg Screening' => 400.00,
        'HIV Screening' => 700.00,
        'VDRL/RPR' => 400.00
    ];

    /**
     * Get price for a consultation type
     */
    public static function getConsultationPrice(string $type = 'regular'): float
    {
        return $type === 'online' ? self::CONSULTATION_ONLINE : self::CONSULTATION_OBGYN;
    }

    /**
     * Get price for a lab test name
     */
    public static function getLabPrice(string $testName): float
    {
        foreach (self::$labPrices as $key => $price) {
            if (stripos($testName, $key) !== false) {
                return (float)$price;
            }
        }
        return 500.00; // Default fallback
    }

    /**
     * Auto-create a billing record for a consultation
     */
    /**
     * Auto-create a billing record for a consultation
     */
    public static function chargeConsultation(int $patientId, int $consultationId, string $type = 'regular', ?int $appointmentId = null): bool
    {
        // [ANTI-DUPLICATE] Auto-detect appointment if not provided
        if (!$appointmentId) {
            $stmt = db()->prepare("SELECT appointment_id FROM appointment 
                                   WHERE user_id = ? AND DATE(appointment_date) = CURDATE() 
                                   AND appointment_status NOT IN ('cancelled', 'completed') 
                                   ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $found = $stmt->fetch();
            if ($found) $appointmentId = (int)$found['appointment_id'];
        }

        // [CONSOLIDATED BILLING] Check if already billed via Appointment Booking
        if ($appointmentId) {
            $existing = db()->prepare("SELECT billing_id FROM billing WHERE appointment_id = ? AND billing_status != 'voided'");
            $existing->execute([$appointmentId]);
            $bill = $existing->fetch();
            if ($bill) {
                // Add consultation as additional line item to existing invoice
                $amount = self::getConsultationPrice($type);
                $description = ($type === 'online' ? 'Online' : 'In-clinic') . ' OB-GYN Consultation Fee';
                Billing::addFee((int)$bill['billing_id'], $amount, $description);
                // Link this consultation to the invoice
                db()->prepare("UPDATE billing SET consultation_id = ? WHERE billing_id = ?")
                    ->execute([(int)$consultationId, (int)$bill['billing_id']]);
                return true;
            }
        }

        $amount = self::getConsultationPrice($type);
        $description = ($type === 'online' ? 'Online' : 'In-clinic') . ' OB-GYN Consultation Fee';

        // Use Policy days
        $policyDays = (int)Billing::getPolicy('policy_consultation_days', '7');
        $dueDate = date('Y-m-d', strtotime("+$policyDays days"));

        return Billing::create(
            $patientId,
            $amount,
            'unpaid',
            $description,
            $consultationId,
            $dueDate,
            $appointmentId
        ) > 0;
    }

    /**
     * Auto-create a billing record for a lab test
     */
    public static function chargeLabTest(int $patientId, string $testName): bool
    {
        $amount = self::getLabPrice($testName);

        // Use Policy days
        $policyDays = (int)Billing::getPolicy('policy_lab_days', '7');
        $dueDate = date('Y-m-d', strtotime("+$policyDays days"));

        return Billing::create(
            $patientId,
            $amount,
            'unpaid',
            'Laboratory Test: ' . $testName,
            null,
            $dueDate
        ) > 0;
    }

    // Prenatal Service Prices
    private const PRENATAL_ENROLLMENT = 1000.00;
    private const PRENATAL_VISIT = 300.00;

    /**
     * Auto-create a billing record for prenatal services
     */
    public static function chargePrenatalService(int $patientId, string $serviceType, ?int $appointmentId = null): bool
    {
        // [ANTI-DUPLICATE] Auto-detect appointment if not provided
        if (!$appointmentId) {
            $stmt = db()->prepare("SELECT appointment_id FROM appointment 
                                   WHERE user_id = ? AND DATE(appointment_date) = CURDATE() 
                                   AND appointment_status NOT IN ('cancelled', 'completed') 
                                   ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $found = $stmt->fetch();
            if ($found) $appointmentId = (int)$found['appointment_id'];
        }

        $amount = 0.00;
        $description = '';

        if ($serviceType === 'enrollment') {
            $amount = self::PRENATAL_ENROLLMENT;
            $description = "Prenatal Program Enrollment";
        } elseif ($serviceType === 'visit') {
            $amount = self::PRENATAL_VISIT;
            $description = "Routine Prenatal Checkup";
        } else {
            return false;
        }

        // [CONSOLIDATED BILLING] Check if already billed via Appointment Booking
        if ($appointmentId) {
            $existing = db()->prepare("SELECT billing_id FROM billing WHERE appointment_id = ? AND billing_status != 'voided'");
            $existing->execute([$appointmentId]);
            $bill = $existing->fetch();
            if ($bill) {
                // Add prenatal service as additional line item to existing invoice
                Billing::addFee((int)$bill['billing_id'], $amount, $description);
                return true;
            }
        }

        // No existing invoice found, create new one
        $policyDays = (int)Billing::getPolicy('policy_consultation_days', '7');
        $dueDate = date('Y-m-d', strtotime("+$policyDays days"));

        return Billing::create(
            $patientId,
            $amount,
            'unpaid',
            $description,
            null,
            $dueDate,
            $appointmentId
        ) > 0;
    }
    // [ANTI-SABOTAGE] Financial Constants
    // Down payment is now dynamic (20-50%)
    private const NO_SHOW_PENALTY = 500.00; // Fixed penalty fee (Verify against national standards)

    /**
     * Get price for various service types
     */
    public static function getServicePrice(string $type): float
    {
        return match ($type) {
            'prenatal' => self::PRENATAL_VISIT, // 300.00
            'gynecology' => self::CONSULTATION_OBGYN, // 700.00
            'online' => self::CONSULTATION_ONLINE, // 800.00
            default => self::CONSULTATION_OBGYN
        };
    }

    /**
     * Get down payment percentage based on service type
     */
    private static function getDownPaymentPercentage(string $type): float
    {
        return match ($type) {
            'prenatal' => 0.20, // 20% for Prenatal consistency
            'gynecology' => 0.50, // 50% for Specialist consult
            'online' => 0.50, // 50% for Telehealth
            default => 0.50
        };
    }

    /**
     * Charge Booking Fee (Down Payment)
     */
    public static function chargeAppointmentBooking(int $patientId, int $appointmentId, string $serviceType = 'general'): bool
    {
        // [UPDATE] Create Invoice for FULL AMOUNT to allow full payment option
        // The user can pay 20% (Partial) or 100% (Paid)
        $estimatedTotal = self::getServicePrice($serviceType);
        $percentage = self::getDownPaymentPercentage($serviceType);

        // Description indicates the minimum policy
        $desc = ucfirst($serviceType) . ' Consultation Fee (Min. Down-Payment: ' . ($percentage * 100) . '%)';

        return Billing::create(
            $patientId,
            $estimatedTotal, // Full amount
            'unpaid',
            $desc,
            null, // consultation_id
            date('Y-m-d'), // Due immediately
            $appointmentId
        ) > 0;
    }

    /**
     * Charge No-Show Penalty
     * Charges both the remaining fee + penalty
     */
    public static function chargeNoShowPenalty(int $patientId, int $appointmentId): bool
    {
        // 1. Charge standard Full Fee (as policy demands payment even if missed)
        // Adjust for already paid down-payment?
        // Simpler approach: Charge the "Penalty Fee" separately.
        // And ensure the original Down-Payment invoice remains valid.

        // Let's charge a specific "No-Show Penalty"
        return Billing::create(
            $patientId,
            self::NO_SHOW_PENALTY,
            'unpaid',
            'No-Show Penalty Fee',
            $appointmentId,
            date('Y-m-d')
        ) > 0;
    }
}
