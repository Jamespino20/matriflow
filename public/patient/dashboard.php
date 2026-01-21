<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once APP_PATH . '/models/LaboratoryTest.php'; // Ensure model is loaded

Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$stats = PatientController::getDashboardStats((int)$u['user_id']);
$patient = $stats['patient'] ?? null;
$appointments = $stats['appointments'] ?? [];
$upcomingCount = $stats['upcomingCount'] ?? 0;
$newLabsCount = $stats['newLabsCount'] ?? 0;
$unreadMsgCount = $stats['unreadMsgCount'] ?? 0;

// Gap Analysis Remediation: Fetch Active Pregnancy for Dashboard
require_once APP_PATH . '/controllers/ConsultationController.php';
$userId = (int)$u['user_id'];
$activePregnancy = ConsultationController::getActivePregnancy($userId);

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 20px;">
    <div class="left-column">
        <div class="card" style="background: linear-gradient(135deg, #14457b 0%, #1e5ba0 100%); color: white; border: none; padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin: 0; font-size: 28px;">Hello, <?= e($u['first_name']) ?>!</h1>
                    <p style="margin: 10px 0 0; opacity: 0.9;">Welcome back to your health portal. You have <strong><?= $upcomingCount ?></strong> upcoming appointments this week.</p>
                </div>
                <div class="quote-box" style="max-width: 300px; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; font-style: italic; font-size: 13px; line-height: 1.5; border: 1px solid rgba(255,255,255,0.2);">
                    "<?= $stats['quote'] ?>"
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 20px;">Weight Tracking (kg)</h3>
            <div style="height: 300px;">
                <canvas id="weightChart"></canvas>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Recent Appointments</h3>
                <a href="<?= base_url('/public/patient/appointments.php') ?>" style="font-size: 13px; color: var(--primary); font-weight: 600;">View All</a>
            </div>
            <?php if (empty($appointments)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 20px;">No recent appointments.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= date('M j, Y - g:i A', strtotime($appt['appointment_date'])) ?></td>
                                <td><?= e($appt['appointment_purpose']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $appt['appointment_status'] === 'scheduled' ? 'info' : 'success' ?>">
                                        <?= ucfirst($appt['appointment_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="right-column">
        <div class="card">
            <h3 style="margin-bottom: 15px;">Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" style="width: 100%; border-radius: 10px; justify-content: flex-start; padding: 12px 15px;" onclick="window.location.href='<?= base_url('/public/patient/appointments.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 10px;">add_circle</span>
                    Book Appointment
                </button>
                <button class="btn btn-secondary" style="width: 100%; border-radius: 10px; justify-content: flex-start; padding: 12px 15px;" onclick="window.location.href='<?= base_url('/public/shared/messages.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 10px;">chat</span>
                    Message Doctor
                </button>
            </div>
        </div>

        <?php
        $displayAppt = $stats['nextAppt'];
        $isRecommendation = false;

        if (!$displayAppt && $activePregnancy && $activePregnancy['next_visit_due']) {
            $displayAppt = [
                'appointment_date' => $activePregnancy['next_visit_due'] . ' 09:00:00', // Default morning time
                'appointment_purpose' => 'Recommended Follow-up'
            ];
            $isRecommendation = true;
        }

        if ($displayAppt):
        ?>
            <div class="card" style="margin-top: 20px; background: #fff; border-left: 4px solid <?= $isRecommendation ? 'var(--info)' : 'var(--success)' ?>;">
                <h3 style="margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">
                    <?= $isRecommendation ? 'Recommended Visit' : 'Next Appointment' ?>
                </h3>
                <div id="appt-countdown" style="font-size: 24px; font-weight: 700; color: <?= $isRecommendation ? 'var(--info)' : 'var(--success)' ?>;" data-date="<?= $displayAppt['appointment_date'] ?>">
                    Loading...
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                    <?= date('M j, Y', strtotime($displayAppt['appointment_date'])) ?><?= !$isRecommendation ? ' - ' . date('g:i A', strtotime($displayAppt['appointment_date'])) : '' ?>
                </div>
                <div style="margin-top: 8px; font-size: 11px; color: #888; font-style: italic;">
                    *<?= e($displayAppt['appointment_purpose']) ?>
                </div>
                <?php if ($isRecommendation): ?>
                    <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                        <input type="hidden" name="action" value="confirm_recommendation">
                        <input type="hidden" name="date" value="<?= date('Y-m-d', strtotime($displayAppt['appointment_date'])) ?>">
                        <input type="hidden" name="service_type" value="prenatal"> <!-- Default for recommendations -->
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%; font-size: 11px;">Book This Date</button>
                    </form>
                <?php endif; ?>
            </div>
            <script>
                function updateCountdown() {
                    const el = document.getElementById('appt-countdown');
                    if (!el) return;
                    const target = new Date(el.dataset.date).getTime();
                    const now = new Date().getTime();
                    const diff = target - now;

                    if (diff <= 0) {
                        el.textContent = "Schedule Today";
                        return;
                    }

                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    let str = "";
                    if (days > 0) str += days + "d ";
                    str += hours + "h " + minutes + "m " + seconds + "s";
                    el.textContent = str;
                }
                setInterval(updateCountdown, 1000);
                updateCountdown();
            </script>
        <?php endif; ?>

        <?php if ($activePregnancy): ?>
            <div class="card" style="margin-top: 20px; background: #fff; border-left: 4px solid var(--primary);">
                <h3 style="margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">Pregnancy Progress (Active)</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                    <?= "{$activePregnancy['gestational_age']['weeks']} Weeks, {$activePregnancy['gestational_age']['days']} Days" ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                    EDC: <?= $activePregnancy['edc_formatted'] ?> (Verified)
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                    <strong style="font-size: 12px; color: #555;">Next Milestone:</strong>
                    <span style="font-size: 12px; color: var(--primary);">
                        <?php
                        if ($activePregnancy['next_visit_due']) {
                            echo "Recommended visit on " . date('M j, Y', strtotime($activePregnancy['next_visit_due']));
                        } else {
                            // Simple logic for next visit based on weeks
                            $weeks = $activePregnancy['gestational_age']['weeks'];
                            $nextVisitInterval = $weeks < 28 ? 4 : ($weeks < 36 ? 2 : 1);
                            echo "Visit in ~$nextVisitInterval weeks";
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php elseif ($stats['baseline']): ?>
            <div class="card" style="margin-top: 20px; background: #fff; border-left: 4px solid var(--secondary);">
                <h3 style="margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">Pregnancy Progress (Baseline)</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--secondary);">
                    <?php
                    $lmp = new DateTime($stats['baseline']['lmp_date']);
                    $now = new DateTime();
                    $diff = $now->diff($lmp);
                    $weeks = floor($diff->days / 7);
                    $days = $diff->days % 7;
                    echo "$weeks Weeks, $days Days";
                    ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                    EDD: <?= date('M j, Y', strtotime($stats['baseline']['estimated_due_date'])) ?>
                </div>
                <div style="margin-top: 8px; font-size: 11px; color: #888; font-style: italic;">
                    *Based on initial baseline. Confirm with doctor for official tracking.
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">Latest Vital Signs</h3>
            <?php
            $v = VitalSigns::getRecentForPatient($userId, 1);
            $lv = $v[0] ?? null;
            if (!$lv):
            ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 10px;">No vitals recorded yet.</p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div style="background: var(--bg-light); padding: 12px; border-radius: 8px; text-align: center;">
                        <small style="color: var(--text-secondary); text-transform: uppercase; font-size: 10px; font-weight: 700; display: block; margin-bottom: 4px;">Blood Pressure</small>
                        <span style="font-weight: 700; font-size: 16px; color: var(--primary);"><?= $lv['systolic_pressure'] . '/' . $lv['diastolic_pressure'] ?></span>
                        <small style="display: block; font-size: 10px; color: var(--text-secondary);">mmHg</small>
                    </div>
                    <div style="background: var(--bg-light); padding: 12px; border-radius: 8px; text-align: center;">
                        <small style="color: var(--text-secondary); text-transform: uppercase; font-size: 10px; font-weight: 700; display: block; margin-bottom: 4px;">Heart Rate</small>
                        <span style="font-weight: 700; font-size: 16px; color: var(--success);"><?= $lv['heart_rate'] ?></span>
                        <small style="display: block; font-size: 10px; color: var(--text-secondary);">bpm</small>
                    </div>
                    <div style="background: var(--bg-light); padding: 12px; border-radius: 8px; text-align: center;">
                        <small style="color: var(--text-secondary); text-transform: uppercase; font-size: 10px; font-weight: 700; display: block; margin-bottom: 4px;">Weight</small>
                        <span style="font-weight: 700; font-size: 16px; color: var(--info);"><?= $lv['weight_kg'] ?></span>
                        <small style="display: block; font-size: 10px; color: var(--text-secondary);">kg</small>
                    </div>
                    <div style="background: var(--bg-light); padding: 12px; border-radius: 8px; text-align: center;">
                        <small style="color: var(--text-secondary); text-transform: uppercase; font-size: 10px; font-weight: 700; display: block; margin-bottom: 4px;">Temp</small>
                        <span style="font-weight: 700; font-size: 16px; color: var(--warning);"><?= $lv['temperature_celsius'] ?>°</span>
                        <small style="display: block; font-size: 10px; color: var(--text-secondary);">C</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">Summary Stats</h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center;">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Available Results</div>
                        <div style="font-weight: 700;"><?= $newLabsCount ?> New Labs</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #fff7ed; color: #c2410c; display: flex; align-items: center; justify-content: center;">
                        <span class="material-symbols-outlined">forum</span>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Unread Messages</div>
                        <div style="font-weight: 700; color: var(--error);"><?= $unreadMsgCount ?> Unread</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('weightChart').getContext('2d');
        const weightData = <?= json_encode($stats['weightHistory']) ?>;

        const labels = weightData.map(d => new Date(d.recorded_at).toLocaleDateString([], {
            month: 'short',
            day: 'numeric'
        }));
        const data = weightData.map(d => d.weight_kg);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Weight (kg)',
                    data: data,
                    borderColor: '#14457b',
                    backgroundColor: 'rgba(20, 69, 123, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#14457b',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: '#f0f2f4'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'dashboard', [
    'title' => 'Dashboard',
    'content' => $content,
]);
