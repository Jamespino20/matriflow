<?php
require_once __DIR__ . '/../../bootstrap.php';

// Only allow admin or CLI
if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (Auth::user()['role'] !== 'admin') {
        die('Unauthorized');
    }
}

$providers = [
    ['Maxicare', 'Maxicare HealthCare Corp.'],
    ['MediCard', 'MediCard Philippines, Inc.'],
    ['IntelliCare', 'Asalus Corporation'],
    ['PhilCare', 'PhilhealthCare, Inc.'],
    ['Cocolife', 'United Coconut Planters Life Assurance Corp.'],
    ['ValuCare', 'Value Care Health Systems, Inc.'],
    ['Avega', 'Avega Managed Care, Inc.'],
    ['Etiqa', 'Etiqa Life and General Assurance Philippines, Inc.'],
    ['Generali', 'Generali Life Assurance Philippines, Inc.'],
    ['EastWest', 'EastWest Healthcare, Inc.'],
    ['CareHealth Plus', 'CareHealth Plus Systems International, Inc.'],
    ['Kaiser', 'Kaiser International Healthgroup, Inc.'],
    ['InLife', 'InLife Health Care'],
    ['Lacson & Lacson', 'Lacson & Lacson Insurance Brokers, Inc.'],
    ['AsianLife', 'AsianLife & General Assurance Corp.'],
    ['Coop Health', '1Cooperative Insurance System of the Philippines'],
    ['HMI', 'Health Maintenance, Inc.'],
    ['Life & Health', 'Life & Health HMI, Inc.'],
    ['Medicare Plus', 'Medicare Plus, Inc.'],
    ['MetroCare', 'MetroCare Health Systems, Inc.'],
    ['MyHealth', 'MyHealth Clinic (HPI)'],
    ['Optimum', 'Optimum Medical and Health Care'],
    ['StarCare', 'StarCare Health Systems, Inc.'],
    ['WellCare', 'WellCare Health Maintenance, Inc.'],
    ['Pacific Cross', 'Pacific Cross Insurance, Inc.']
];

$db = Database::getInstance();
$db->beginTransaction();

try {
    // Clear existing to avoid duplicates if re-run (or just use INSERT IGNORE logic)
    // For seeding 24+, we'll just ensure we have at least these.

    foreach ($providers as $p) {
        $shortName = $p[0];
        $fullName = $p[1];

        // Check if exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM hmo_providers WHERE short_name = ?");
        $stmt->execute([$shortName]);
        if ($stmt->fetchColumn() == 0) {
            HmoProvider::create($fullName, $shortName);
            echo "Added: $shortName\n";
        } else {
            echo "Skipped: $shortName (Already exists)\n";
        }
    }

    $db->commit();
    echo "Seeding completed successfully.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
