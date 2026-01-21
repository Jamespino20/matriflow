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

    public static function getActivePregnancy(int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM pregnancies WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $preg = $stmt->fetch();

        if ($preg) {
            $preg['gestational_age'] = PregnancyCalculator::calculateGestationalAge($preg['lmp_date']);
            $preg['edc_formatted'] = date('M j, Y', strtotime($preg['estimated_due_date']));
        }

        return $preg ?: null;
    }
}
