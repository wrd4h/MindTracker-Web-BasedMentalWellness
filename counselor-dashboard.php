<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

if (($_SESSION['role'] ?? 'student') !== 'counselor') {
    header("Location: dashboard.php");
    exit();
}

$counselor_id = $_SESSION['user_id'];
$counselor_name = $_SESSION['full_name'] ?? 'Counsellor Unit';

// ========================================
// Total students
// ========================================
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch_assoc()['total'];

// ========================================
// Total checkins
// ========================================
$stmt = $conn->query("SELECT COUNT(*) as total FROM checkins");
$total_checkins = $stmt->fetch_assoc()['total'];

// ========================================
// Average wellness score (exclude 0)
// ========================================
$stmt = $conn->query("SELECT AVG(score) as avg_score FROM wellness_scores WHERE score > 0");
$avg_wellness = round($stmt->fetch_assoc()['avg_score'] ?? 0, 1);

// ========================================
// High-risk students (unique)
// ========================================
$stmt = $conn->query("
    SELECT u.user_id, u.full_name, u.email, u.matric_no,
           MAX(c.mood) as mood, 
           MAX(c.stress_level) as stress_level, 
           MAX(c.created_at) as created_at
    FROM users u
    JOIN checkins c ON c.user_id = u.user_id
    WHERE (c.mood IN ('very_sad', 'sad') OR c.stress_level >= 8)
    AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY u.user_id
    ORDER BY created_at DESC
");
$high_risk = $stmt->fetch_all(MYSQLI_ASSOC);
$high_risk_count = count($high_risk);

// ========================================
// Mood distribution
// ========================================
$stmt = $conn->query("
    SELECT mood, COUNT(*) as total
    FROM checkins
    GROUP BY mood
");
$mood_distribution = ['happy' => 0, 'calm' => 0, 'neutral' => 0, 'sad' => 0, 'very_sad' => 0];
while ($row = $stmt->fetch_assoc()) {
    if (isset($mood_distribution[$row['mood']])) {
        $mood_distribution[$row['mood']] = $row['total'];
    }
}

// ========================================
// Weekly mood trend (last 7 days)
// ========================================
$stmt = $conn->query("
    SELECT DATE(created_at) as day, 
           AVG(CASE mood 
               WHEN 'happy' THEN 10 
               WHEN 'calm' THEN 8 
               WHEN 'neutral' THEN 6 
               WHEN 'sad' THEN 4 
               WHEN 'very_sad' THEN 2 
               ELSE 5 END) as avg_mood
    FROM checkins
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$weekly_trend = $stmt->fetch_all(MYSQLI_ASSOC);

$weekly_labels = [];
$weekly_mood_data = [];
foreach ($weekly_trend as $row) {
    $weekly_labels[] = date('D', strtotime($row['day']));
    $weekly_mood_data[] = round($row['avg_mood'], 1);
}

// ========================================
// Recent notes
// ========================================
$stmt = $conn->prepare("
    SELECT cn.*, u.full_name as student_name
    FROM counselor_notes cn
    JOIN users u ON u.user_id = cn.student_id
    WHERE cn.counselor_id = ?
    ORDER BY cn.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$recent_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========================================
// Handle add note
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $student_id = intval($_POST['student_id']);
    $note = trim($_POST['note']);
    
    if (!empty($note) && $student_id > 0) {
        $stmt = $conn->prepare("INSERT INTO counselor_notes (counselor_id, student_id, note) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $counselor_id, $student_id, $note);
        $stmt->execute();
        $stmt->close();
        header("Location: counselor-dashboard.php?success=1");
        exit();
    }
}

$mood_emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];

$page_title = 'Counsellor Dashboard';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1>Welcome, <span><?php echo htmlspecialchars($counselor_name); ?></span> 👋</h1>
    <p class="subtitle">Overview of Student Wellness at Universiti.</p>
</div>

    <a href="send-reminder.php" class="btn-primary-custom" style="background: #f39c12;" target="_blank">
        <i class="bi bi-envelope"></i> Send Reminder
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="message-success">✅ Note saved successfully!</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
    </div>
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
            <div class="stat-label">Avg Wellness Score</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fdecea; color: #e74c3c;"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-number" style="color: #e74c3c;"><?php echo $high_risk_count; ?></div>
            <div class="stat-label">High-Risk Students</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- High Risk Students -->
    <div class="col-md-7">
        <div class="card-box">
            <h5><i class="bi bi-exclamation-triangle" style="color: #e74c3c;"></i> Students Needing Attention</h5>
            <?php if (count($high_risk) > 0): ?>
                <?php foreach ($high_risk as $student): ?>
                    <div class="student-row high-risk">
                        <div class="student-info">
                            <div class="student-emoji"><?php echo $mood_emojis[$student['mood']] ?? '😐'; ?></div>
                            <div>
                                <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                <div class="student-meta">
                                    <?php echo htmlspecialchars($student['matric_no'] ?? 'N/A'); ?> • 
                                    Stress: <?php echo $student['stress_level']; ?>/10 • 
                                    <?php echo date('d M Y', strtotime($student['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <a href="counselor-student-detail.php?id=<?php echo $student['user_id']; ?>" 
                           class="btn-note" style="text-decoration: none;">
                            <i class="bi bi-eye"></i> View
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center py-3">🎉 No high-risk students this week. All good!</p>
            <?php endif; ?>
        </div>

        <!-- Weekly Mood Trend -->
        <div class="card-box">
            <h5><i class="bi bi-graph-up" style="color: #2E7D5B;"></i> Overall Student Mood Trend (Last 7 Days)</h5>
            <?php if (count($weekly_labels) > 0): ?>
                <canvas id="counselorChart" style="max-height: 220px;"></canvas>
            <?php else: ?>
                <p class="text-muted text-center py-3">No data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mood Distribution & Notes -->
    <div class="col-md-5">
        <div class="card-box">
            <h5><i class="bi bi-pie-chart" style="color: #2E7D5B;"></i> Mood Distribution</h5>
            <?php if (array_sum($mood_distribution) > 0): ?>
                <canvas id="counselorPie" style="max-height: 220px;"></canvas>
            <?php else: ?>
                <p class="text-muted text-center py-3">No data available.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Notes -->
        <div class="card-box">
            <h5><i class="bi bi-journal-text" style="color: #2E7D5B;"></i> Your Recent Notes</h5>
            <?php if (count($recent_notes) > 0): ?>
                <?php foreach ($recent_notes as $note): ?>
                    <div class="note-item">
                        <div class="note-header">
                            📌 <?php echo htmlspecialchars($note['student_name']); ?> • 
                            <?php echo date('d M Y', strtotime($note['created_at'])); ?>
                        </div>
                        <div class="note-text"><?php echo htmlspecialchars($note['note']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center py-3">No notes yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($weekly_labels) > 0): ?>
    new Chart(document.getElementById('counselorChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weekly_labels); ?>,
            datasets: [{
                label: 'Avg Mood Score',
                data: <?php echo json_encode($weekly_mood_data); ?>,
                borderColor: '#2E7D5B',
                backgroundColor: 'rgba(46, 125, 91, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#2E7D5B',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { min: 0, max: 10, ticks: { stepSize: 2 } } },
            plugins: { legend: { display: false } }
        }
    });
    <?php endif; ?>

    <?php if (array_sum($mood_distribution) > 0): ?>
    new Chart(document.getElementById('counselorPie'), {
        type: 'doughnut',
        data: {
            labels: ['Happy', 'Calm', 'Neutral', 'Sad', 'Very Sad'],
            datasets: [{
                data: [
                    <?php echo $mood_distribution['happy']; ?>,
                    <?php echo $mood_distribution['calm']; ?>,
                    <?php echo $mood_distribution['neutral']; ?>,
                    <?php echo $mood_distribution['sad']; ?>,
                    <?php echo $mood_distribution['very_sad']; ?>
                ],
                backgroundColor: ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'],
                borderWidth: 2,
                borderColor: 'white'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
        }
    });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>