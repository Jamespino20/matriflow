<?php

declare(strict_types=1);

final class ConsultationController
{
    public static function create(int $patientId, int $doctorId, array $data): int
    {
        // Add basic validation if needed
        return Consultation::create($patientId, $doctorId, $data);
    }

    public static function listHistory(int $patientId): array
    {
        return Consultation::listByPatient($patientId);
    }

    public static function getConsultationTypes(): array
    {
        return ['general', 'prenatal', 'gynecology', 'procedure'];
    }

    public static function getActivePregnancy(int $patientId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM pregnancy_history WHERE patient_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$patientId]);
        $preg = $stmt->fetch();

        if ($preg) {
            $preg['gestational_age'] = PregnancyCalculator::calculateGestationalAge($preg['last_menstrual_period']);
            $preg['edc_formatted'] = date('M j, Y', strtotime($preg['estimated_date_confinement']));
        }

        return $preg ?: null;
    }
}
