<?php

declare(strict_types=1);

final class PregnancyCalculator
{
    /**
     * Calculate EDC using Naegele's Rule: LMP + 1 year - 3 months + 7 days
     */
    public static function calculateEDC(string $lmpDate): string
    {
        $lmp = new DateTime($lmpDate);
        // Naegele's Rule
        $edc = clone $lmp;
        $edc->modify('+1 year');
        $edc->modify('-3 months');
        $edc->modify('+7 days');

        return $edc->format('Y-m-d');
    }

    /**
     * Calculate Gestational Age in weeks and days
     */
    public static function calculateGestationalAge(string $lmpDate, string $targetDate = 'now'): array
    {
        $lmp = new DateTime($lmpDate);
        $target = new DateTime($targetDate);

        if ($target < $lmp) {
            return ['weeks' => 0, 'days' => 0];
        }

        $interval = $lmp->diff($target);
        $totalDays = $interval->days;

        $weeks = (int) floor($totalDays / 7);
        $days = $totalDays % 7;

        return ['weeks' => $weeks, 'days' => $days];
    }

    /**
     * Generate standard WHO-aligned prenatal visit schedule
     * 0-28 weeks: Every 4 weeks
     * 28-36 weeks: Every 2 weeks
     * 36+ weeks: Weekly
     */
    public static function generateSchedule(string $lmpDate): array
    {
        $schedule = [];
        $lmp = new DateTime($lmpDate);
        $edc = new DateTime(self::calculateEDC($lmpDate));

        $currentDate = clone $lmp;
        $currentDate->modify('+8 weeks'); // First visit usually around 8 weeks

        while ($currentDate <= $edc) {
            $ga = self::calculateGestationalAge($lmpDate, $currentDate->format('Y-m-d'));
            $weeks = $ga['weeks'];

            $schedule[] = [
                'week' => $weeks,
                'date_start' => $currentDate->format('Y-m-d'),
                'date_end' => (clone $currentDate)->modify('+6 days')->format('Y-m-d')
            ];

            // Determine interval
            if ($weeks < 28) {
                $currentDate->modify('+4 weeks');
            } elseif ($weeks < 36) {
                $currentDate->modify('+2 weeks');
            } else {
                $currentDate->modify('+1 week');
            }
        }

        return $schedule;
    }
}
