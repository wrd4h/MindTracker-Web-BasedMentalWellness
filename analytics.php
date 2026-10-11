<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';

$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM wellness_scores WHERE user_id = ? ORDER BY score_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$scores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$labels = [];
$mood_scores = [];
$stress_levels = [];
$wellness_scores = [];
$mood_counts = ['happy' => 0, 'calm' => 0, 'neutral' => 0, 'sad' => 0, 'very_sad' => 0];

foreach ($checkins as $checkin) {
    $labels[] = date('d M', strtotime($checkin['created_at']));
    $mood = isset($checkin['mods']) ? $checkin['mods'] : 'neutral';
    $mood_map = ['happy' => 10, 'calm' => 8, 'neutral' => 6, 'sad' => 4, 'very_sad' => 2];
    $mood_scores[] = isset($mood_map[$mood]) ? $mood_map[$mood] : 5;
    $stress_levels[] = isset($checkin['stress_level']) ? $checkin['stress_level'] : 5;
    if (isset($mood_counts[$mood])) $mood_counts[$mood]++;
}

$score_map = [];
foreach ($scores as $score) {
    $score_map[$score['checkin_id']] = $score['score'];
}
foreach ($checkins as $checkin) {
    $wellness_scores[] = isset($score_map[$checkin['checkin_id']]) ? $score_map[$checkin['checkin_id']] : null;
}

$total_checkins = count($checkins);
$avg_stress = $total_checkins > 0 ? round(array_sum($stress_levels) / $total_checkins, 1) : 0;
$valid_wellness = array_filter($wellness_scores);
$avg_wellness = $total_checkins > 0 && count($valid_wellness) > 0 ? round(array_sum($valid_wellness) / count($valid_wellness), 1) : 0;
$most_common_mood = array_keys($mood_counts, max($mood_counts))[0] ?? 'neutral';

$mood_emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];
$mood_labels = ['happy' => 'Happy', 'calm' => 'Calm', 'neutral' => 'Neutral', 'sad' => 'Sad', 'very_sad' => 'Very Sad'];

$page_title = 'Analytics';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-bar-chart" style="color: #6BAF7B;"></i> My Insights</h1>
    <p class="subtitle">Understand your mental wellness patterns, <?php echo htmlspecialchars($user_name); ?>.</p>
</div>

<?php if ($total_checkins > 0): ?>

<!-- TABS -->
<div class="analytics-tabs mb-4">
    <button class="tab-btn active" onclick="showTab('mood', event)">
        <i class="bi bi-emoji-smile"></i> Mood Trends
    </button>
    <button class="tab-btn" onclick="showTab('stress', event)">
        <i class="bi bi-graph-down"></i> Stress Level
    </button>
    <button class="tab-btn" onclick="showTab('wellness', event)">
        <i class="bi bi-heart-pulse"></i> Wellness Score
    </button>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-number"><?php echo $total_checkins; ?></div>
            <div class="stat-label">Total Check-ins</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-heart-pulse"></i></div>
            <div class="stat-number"><?php echo $avg_wellness; ?>%</div>
            <div class="stat-label">Avg Wellness</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-emoji-smile"></i></div>
            <span class="stat-mood"><?php echo $mood_emojis[$most_common_mood] ?? '😊'; ?></span>
            <div class="stat-label"><?php echo $mood_labels[$most_common_mood] ?? 'Mood'; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
            <div class="stat-number"><?php echo $avg_stress; ?>/10</div>
            <div class="stat-label">Avg Stress</div>
        </div>
    </div>
</div>

<!-- TAB 1: Mood Trends -->
<div class="tab-content" id="tab-mood">
    <div class="row g-3">
        <div class="col-md-8">
            <div class="card-box">
                <h5><i class="bi bi-graph-up" style="color: #6BAF7B;"></i> Mood Trend</h5>
                <canvas id="moodChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-box">
                <h5><i class="bi bi-pie-chart" style="color: #6BAF7B;"></i> Mood Distribution</h5>
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Stress Level -->
<div class="tab-content" id="tab-stress" style="display: none;">
    <div class="card-box">
        <h5><i class="bi bi-graph-down" style="color: #e74c3c;"></i> Stress Trend</h5>
        <canvas id="stressChart"></canvas>
    </div>
</div>

<!-- TAB 3: Wellness Score -->
<div class="tab-content" id="tab-wellness" style="display: none;">
    <div class="card-box">
        <h5><i class="bi bi-heart-pulse" style="color: #6BAF7B;"></i> Wellness Score Trend</h5>
        <canvas id="wellnessChart"></canvas>
    </div>
</div>

<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">📊</div>
        <h4>No Data Yet</h4>
        <p>Start logging your mood to see insights and patterns!</p>
        <a href="checkin.php" class="btn-primary-custom mt-3">Log Your Mood</a>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<script>
function showTab(tabName, event) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).style.display = 'block';
    event.target.closest('.tab-btn').classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($total_checkins > 0): ?>
    
    new Chart(document.getElementById('moodChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Mood Score',
                data: <?php echo json_encode($mood_scores); ?>,
                borderColor: '#6BAF7B',
                backgroundColor: 'rgba(107, 175, 123, 0.1)',
                tension: 0.3, fill: true,
                pointBackgroundColor: '#6BAF7B',
                pointBorderColor: 'white', pointBorderWidth: 2, pointRadius: 5
            }]
        },
        options: { responsive: true, maintainAspectRatio: true,
            scales: { y: { min: 0, max: 10, ticks: { stepSize: 2 } } },
            plugins: { legend: { display: false } }
        }
    });
    
    const moodCounts = <?php echo json_encode($mood_counts); ?>;
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Happy', 'Calm', 'Neutral', 'Sad', 'Very Sad'],
            datasets: [{
                data: [moodCounts.happy, moodCounts.calm, moodCounts.neutral, moodCounts.sad, moodCounts.very_sad],
                backgroundColor: ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'],
                borderWidth: 2, borderColor: 'white'
            }]
        },
        options: { responsive: true, maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
        }
    });
    
    new Chart(document.getElementById('stressChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Stress Level',
                data: <?php echo json_encode($stress_levels); ?>,
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                tension: 0.3, fill: true,
                pointBackgroundColor: '#e74c3c',
                pointBorderColor: 'white', pointBorderWidth: 2, pointRadius: 5
            }]
        },
        options: { responsive: true, maintainAspectRatio: true,
            scales: { y: { min: 0, max: 10, ticks: { stepSize: 2 } } },
            plugins: { legend: { display: false } }
        }
    });
    
    new Chart(document.getElementById('wellnessChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Wellness Score',
                data: <?php echo json_encode($wellness_scores); ?>,
                borderColor: '#6BAF7B',
                backgroundColor: 'rgba(107, 175, 123, 0.1)',
                tension: 0.3, fill: true,
                pointBackgroundColor: '#6BAF7B',
                pointBorderColor: 'white', pointBorderWidth: 2, pointRadius: 5,
                borderDash: [5, 5]
            }]
        },
        options: { responsive: true, maintainAspectRatio: true,
            scales: { y: { min: 0, max: 100, ticks: { stepSize: 20 } } },
            plugins: { legend: { display: false } }
        }
    });
    
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>