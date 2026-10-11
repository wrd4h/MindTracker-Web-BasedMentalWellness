<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';

// Get current month
$month = date('Y-m');
$month_name = date('F Y');

// Get all check-ins for this month
$stmt = $conn->prepare("
    SELECT * FROM checkins 
    WHERE user_id = ? 
    AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ORDER BY created_at ASC
");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_days = count($checkins);
$mood_counts = ['happy' => 0, 'calm' => 0, 'neutral' => 0, 'sad' => 0, 'very_sad' => 0];
$stress_days = 0;
$high_stress_days = 0;
$total_stress = 0;

foreach ($checkins as $c) {
    $mood = $c['mods'] ?? $c['mood'] ?? 'neutral';
    if (isset($mood_counts[$mood])) $mood_counts[$mood]++;
    
    $stress = $c['stress_level'];
    $total_stress += $stress;
    
    if ($stress >= 7) $high_stress_days++;
    if ($stress >= 4) $stress_days++;
}

$avg_stress = $total_days > 0 ? round($total_stress / $total_days, 1) : 0;

// Generate summary message
$summary = "";
$recommendation = "";
$alert_level = "normal"; // normal, warning, danger

if ($total_days == 0) {
    $summary = "No mood has been logged this month.";
    $recommendation = "Start the wellness journey by logging the first mood!";
} elseif ($high_stress_days >= 10) {
    $alert_level = "danger";
    $summary = "Out of $total_days days logged, high stress was experienced on $high_stress_days days. The average stress level is $avg_stress/10.";
    $recommendation = "⚠️ The stress levels have been consistently high this month. We strongly recommend consulting a professional psychologist or counsellor. Please reach out to the university counselling unit for support. The user is not alone.";
} elseif ($stress_days >= 10) {
    $alert_level = "warning";
    $summary = "Out of $total_days days logged, stress was experienced on $stress_days days. The average stress level is $avg_stress/10.";
    $recommendation = "💛 Stress has been experienced quite often. We recommend talking to a friend, taking regular breaks, and practising self-care. If stress continues, consider speaking to a counsellor.";
} else {
    $summary = "Out of $total_days days logged. The average stress level is $avg_stress/10.";
    $recommendation = "🌱 The emotional patterns look relatively stable. We encourage continuing daily check-ins and taking care of mental well-being!";
}

$mood_emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];

$page_title = 'Monthly Summary';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-calendar-month" style="color: #2E7D5B;"></i> Monthly Summary</h1>
    <p class="subtitle">A mental wellness report for <?php echo $month_name; ?>.</p>
</div>

<?php if ($total_days == 0): ?>
    <div class="empty-state">
        <div class="big-icon">📅</div>
        <h4>No Data Yet</h4>
        <p>Start logging mood to see the monthly summary!</p>
        <a href="checkin.php" class="btn-primary-custom mt-3">Log Mood</a>
    </div>
<?php else: ?>

    <!-- Alert Banner -->
    <?php if ($alert_level === 'danger'): ?>
        <div class="alert-banner danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>High Stress Alert</strong>
                <p class="mb-0">Stress levels have been consistently high this month. Professional help is strongly recommended.</p>
            </div>
        </div>
    <?php elseif ($alert_level === 'warning'): ?>
        <div class="alert-banner warning">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>
                <strong>Moderate Stress Notice</strong>
                <p class="mb-0">Stress has been experienced quite often. Self-care is important.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Card -->
    <div class="summary-card">
        <h3>📊 Monthly Summary</h3>
        <p style="font-size: 16px; line-height: 1.8; color: #555;">
            Thank you for your response. <?php echo $summary; ?>
        </p>
    </div>

    <!-- Recommendation -->
    <div class="recommendation-card" style="border-left-color: <?php echo $alert_level === 'danger' ? '#e74c3c' : ($alert_level === 'warning' ? '#f1c40f' : '#2E7D5B'); ?>;">
        <h3>💡 Our Recommendation</h3>
        <p style="font-size: 15px; line-height: 1.7; color: #555;">
            <?php echo $recommendation; ?>
        </p>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-number"><?php echo $total_days; ?></div>
                <div class="stat-label">Days Logged</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
                <div class="stat-number"><?php echo $avg_stress; ?></div>
                <div class="stat-label">Avg Stress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: #fdecea; color: #e74c3c;"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="stat-number" style="color: #e74c3c;"><?php echo $high_stress_days; ?></div>
                <div class="stat-label">High Stress Days</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-heart"></i></div>
                <div class="stat-number"><?php echo $stress_days; ?></div>
                <div class="stat-label">Stress Days</div>
            </div>
        </div>
    </div>

    <!-- Mood Distribution -->
    <div class="card-box">
        <h5>Mood Distribution</h5>
        <div class="mood-distribution">
            <?php foreach ($mood_counts as $mood => $count): ?>
                <?php if ($count > 0): ?>
                    <div class="mood-dist-item">
                        <span class="mood-emoji"><?php echo $mood_emojis[$mood]; ?></span>
                        <div>
                            <div class="mood-label"><?php echo ucfirst($mood); ?></div>
                            <div class="mood-count"><?php echo $count; ?> days</div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<div class="mt-4 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>