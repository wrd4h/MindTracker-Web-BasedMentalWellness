<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';
$today = date('Y-m-d');

// Get today's checkin
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) = ? ORDER BY checkin_id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$todayMood = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get wellness score for today
$wellness_score = null;
if ($todayMood) {
    $stmt = $conn->prepare("SELECT score FROM wellness_scores WHERE user_id = ? AND checkin_id = ?");
    $stmt->bind_param("ii", $user_id, $todayMood['checkin_id']);
    $stmt->execute();
    $score_row = $stmt->get_result()->fetch_assoc();
    $wellness_score = $score_row['score'] ?? null;
    $stmt->close();
}

// Get weekly checkins
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) >= DATE_SUB(?, INTERVAL 6 DAY) ORDER BY created_at ASC");
$stmt->bind_param("ss", $user_id, $today);
$stmt->execute();
$weeklyMoods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total check-ins
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalCheckins = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Average stress
$stmt = $conn->prepare("SELECT AVG(stress_level) as avg_stress FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_stress = round($stmt->get_result()->fetch_assoc()['avg_stress'] ?? 0, 1);
$stmt->close();

// Quote
$stmt = $conn->query("SELECT * FROM quotes ORDER BY RAND() LIMIT 1");
$quote = $stmt->fetch_assoc();
$stmt->close();

// Personalized recommendation
$personalized = null;
if ($todayMood) {
    $mood_key = $todayMood['mods'] ?? $todayMood['mood'] ?? 'neutral';
    $stress_key = $todayMood['stress_level'] ?? 5;
    $personalized = getPersonalizedRecommendation($mood_key, $stress_key);
}

// Day streak
$streak = 0;
$current = $today;
$stmt = $conn->prepare("SELECT DATE(created_at) as log_date FROM checkins WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($dates as $row) {
    if ($row['log_date'] == $current) {
        $streak++;
        $current = date('Y-m-d', strtotime($current . ' -1 day'));
    } else {
        break;
    }
}

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<div class="container">

<!-- Welcome -->
<div class="dashboard-header">
    <div>
        <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <span><?php echo htmlspecialchars($user_name); ?></span>! 🌿</h1>
        <p class="subtitle">How are you feeling today? Take a moment to check in with yourself.</p>
    </div>
    <a href="checkin.php" class="btn-primary-custom">
        <i class="bi bi-plus-circle"></i> Daily Check-In
    </a>
</div>

<!-- Quote -->
<?php if ($quote): ?>
    <div class="quote-card mb-4">
        <p class="quote-text mb-1">"<?php echo htmlspecialchars($quote['quote_text'] ?? $quote['quote'] ?? ''); ?>"</p>
        <?php if (isset($quote['author']) && $quote['author']): ?>
            <p class="quote-author mb-0">— <?php echo htmlspecialchars($quote['author']); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-checkin">
            <div class="stat-emoji">💗</div>
            <div class="stat-number"><?php echo $totalCheckins; ?></div>
            <div class="stat-label">Total Check-ins</div>
            <small>Keep it up!</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-wellness">
            <div class="stat-emoji">🌿</div>
            <div class="stat-number"><?php echo $wellness_score ?? '-'; ?><span style="font-size: 18px;">%</span></div>
            <div class="stat-label">Wellness Score</div>
            <small>Good progress</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-stress">
            <div class="stat-emoji">💧</div>
            <div class="stat-number"><?php echo $avg_stress; ?><span style="font-size: 18px;">/10</span></div>
            <div class="stat-label">Avg. Stress</div>
            <small><?php echo $avg_stress <= 3 ? 'Low' : ($avg_stress <= 6 ? 'Moderate' : 'High'); ?></small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-streak">
            <div class="stat-emoji">🔥</div>
            <div class="stat-number"><?php echo $streak; ?> <span style="font-size: 16px;">Days</span></div>
            <div class="stat-label">Day Streak</div>
            <small>You're amazing!</small>
        </div>
    </div>
</div>

<!-- Today's Mood + Recommendation -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="mood-card mood-card-today">
            <h5>Today's Mood</h5>
            <?php if ($todayMood): ?>
                <?php
                $emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];
                $mood_key = $todayMood['mods'] ?? $todayMood['mood'] ?? 'neutral';
                ?>
                <div class="big-emoji"><?php echo $emojis[$mood_key] ?? '😊'; ?></div>
                <h4 class="mt-2"><?php echo ucfirst(str_replace('_', ' ', $mood_key)); ?></h4>
                <p class="text-muted mb-0">Great to see you're feeling good today!</p>
            <?php else: ?>
                <div class="big-emoji">🤔</div>
                <p class="text-muted">No mood logged today.</p>
                <a href="checkin.php" class="btn-primary-custom mt-2">Log Mood</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <?php if ($personalized): ?>
            <div class="recommendation-box" style="background: <?php echo $personalized['color']; ?>;">
                <h4><?php echo $personalized['title']; ?></h4>
                <p class="rec-text"><?php echo htmlspecialchars($personalized['message']); ?></p>
            </div>
        <?php else: ?>
            <div class="mood-card" style="height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                <div class="big-emoji">🧘</div>
                <p class="text-muted">Log your mood to get personalized recommendations.</p>
                <a href="checkin.php" class="btn-primary-custom" style="align-self: center;">Log Mood</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Weekly Chart -->
<div class="card-box mb-4">
    <h5><i class="bi bi-graph-up" style="color: #2E7D5B;"></i> Weekly Mood Trend</h5>
    <?php if (count($weeklyMoods) > 0): ?>
        <canvas id="weeklyChart" style="max-height: 250px;"></canvas>
    <?php else: ?>
        <p class="text-muted text-center py-3">No data yet. Start logging your mood!</p>
    <?php endif; ?>
    <!-- Suggested Activities -->
<div class="card-box mt-4">
    <h5><i class="bi bi-lightbulb" style="color: #2E7D5B;"></i> Suggested Activities for You</h5>
    <div class="row g-3">
        <?php
        $stmt = $conn->query("SELECT * FROM wellness_tips ORDER BY RAND() LIMIT 3");
        $suggested_tips = $stmt->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($suggested_tips as $tip):
        ?>
            <div class="col-md-4">
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="bi <?php echo htmlspecialchars($tip['icon'] ?? 'bi-lightbulb'); ?>"></i>
                    </div>
                    <span class="tip-category"><?php echo htmlspecialchars($tip['category'] ?? 'Wellness'); ?></span>
                    <h5><?php echo htmlspecialchars($tip['title']); ?></h5>
                    <p><?php echo htmlspecialchars(substr($tip['description'], 0, 100)); ?>...</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($weeklyMoods) > 0): ?>
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const moodData = <?php echo json_encode($weeklyMoods); ?>;
    
    const labels = moodData.map(log => {
        const d = new Date(log.created_at);
        return d.toLocaleDateString('en-US', { weekday: 'short' });
    });
    
    const moodScores = moodData.map(log => {
        const map = { happy: 10, calm: 8, neutral: 6, sad: 4, very_sad: 2 };
        const moodKey = log.mods || log.mood || 'neutral';
        return map[moodKey] || 5;
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Mood Score',
                data: moodScores,
                borderColor: '#2E7D5B',
                backgroundColor: 'rgba(46, 125, 91, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#2E7D5B',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { min: 0, max: 10, ticks: { stepSize: 2 } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>