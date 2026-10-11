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
$student_id = intval($_GET['id'] ?? 0);

if ($student_id <= 0) {
    header("Location: counselor-students.php");
    exit();
}

// ========================================
// Get student info
// ========================================
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header("Location: counselor-students.php");
    exit();
}

// ========================================
// Get mood history (last 30)
// ========================================
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========================================
// Stats
// ========================================
$stmt = $conn->prepare("SELECT COUNT(*) as total, AVG(stress_level) as avg_stress FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(score) as avg_wellness FROM wellness_scores WHERE user_id = ? AND score > 0");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$ws = $stmt->get_result()->fetch_assoc();
$avg_wellness = $ws['avg_wellness'] ? round($ws['avg_wellness']) : 0;
$stmt->close();

// ========================================
// Notes for this student
// ========================================
$stmt = $conn->prepare("
    SELECT cn.*, u.full_name as counselor_name 
    FROM counselor_notes cn 
    JOIN users u ON u.user_id = cn.counselor_id 
    WHERE cn.student_id = ? 
    ORDER BY cn.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========================================
// Handle add note
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note']);
    if (!empty($note)) {
        $stmt = $conn->prepare("INSERT INTO counselor_notes (counselor_id, student_id, note) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $counselor_id, $student_id, $note);
        $stmt->execute();
        $stmt->close();
        header("Location: counselor-student-detail.php?id=$student_id&success=1");
        exit();
    }
}

$mood_emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];

$page_title = 'Student Detail';
include 'includes/header.php';
?>

<div class="container">

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1><i class="bi bi-person-circle" style="color: #2E7D5B;"></i> <?php echo htmlspecialchars($student['full_name']); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars($student['email']); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="counselor-student-summary.php?id=<?php echo $student_id; ?>" class="btn-primary-custom" style="background: #A7D7C5;">
            <i class="bi bi-calendar-month"></i> Monthly Summary
        </a>
        <a href="counselor-students.php" class="btn-primary-custom">
            <i class="bi bi-arrow-left"></i> Back to Students
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="message-success">✅ Note saved successfully!</div>
<?php endif; ?>

<!-- Student Info + Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card-box text-center">
            <?php if (!empty($student['profile_photo']) && file_exists('uploads/profile/' . $student['profile_photo'])): ?>
                <img src="uploads/profile/<?php echo htmlspecialchars($student['profile_photo']); ?>" 
                     class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center"
                     style="width: 120px; height: 120px; border: 2px dashed #ccc;">
                    <i class="bi bi-person" style="font-size: 60px; color: #ccc;"></i>
                </div>
            <?php endif; ?>
            <h5 class="mt-3"><?php echo htmlspecialchars($student['full_name']); ?></h5>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($student['matric_no'] ?? '-'); ?></p>
            <p class="text-muted"><?php echo htmlspecialchars($student['faculty'] ?? '-'); ?></p>
        </div>
    </div>

    <div class="col-md-8">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Check-ins</div>
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
                    <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
                    <div class="stat-number"><?php echo round($stats['avg_stress'] ?? 0, 1); ?></div>
                    <div class="stat-label">Avg Stress</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
                    <div class="stat-number"><?php echo count($notes); ?></div>
                    <div class="stat-label">Notes</div>
                </div>
            </div>
        </div>

        <!-- Add Note -->
        <div class="card-box mt-3">
            <h5><i class="bi bi-pencil" style="color: #2E7D5B;"></i> Add Counselling Note</h5>
            <form method="POST">
                <input type="hidden" name="add_note" value="1">
                <textarea name="note" class="journal-textarea" rows="3" placeholder="Write your observation, follow-up plan, or advice..." required></textarea>
                <button type="submit" class="btn-primary-custom mt-2">
                    <i class="bi bi-save"></i> Save Note
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Mood History -->
<div class="card-box mb-4">
    <h5><i class="bi bi-clock-history" style="color: #2E7D5B;"></i> Recent Mood History</h5>
    <?php if (count($checkins) > 0): ?>
        <?php foreach ($checkins as $checkin): ?>
            <?php 
                $mood_key = $checkin['mood'] ?? $checkin['mods'] ?? 'neutral';
            ?>
            <div class="student-row" style="border-left-color: #2E7D5B;">
                <div class="student-info">
                    <div class="student-emoji"><?php echo $mood_emojis[$mood_key] ?? '😐'; ?></div>
                    <div>
                        <div class="student-name"><?php echo ucfirst(str_replace('_', ' ', $mood_key)); ?></div>
                        <div class="student-meta">
                            <?php echo date('d M Y, h:i A', strtotime($checkin['created_at'])); ?> • 
                            Stress: <?php echo $checkin['stress_level']; ?>/10
                        </div>
                        <?php if (!empty($checkin['journal_entry'])): ?>
                            <div class="student-meta mt-1">📝 <?php echo htmlspecialchars(substr($checkin['journal_entry'], 0, 100)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center py-3">No check-ins yet.</p>
    <?php endif; ?>
</div>

<!-- Notes History -->
<div class="card-box">
    <h5><i class="bi bi-journal-text" style="color: #2E7D5B;"></i> Counselling Notes</h5>
    <?php if (count($notes) > 0): ?>
        <?php foreach ($notes as $note): ?>
            <div class="note-item">
                <div class="note-header">
                    📌 <?php echo htmlspecialchars($note['counselor_name']); ?> • 
                    <?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?>
                </div>
                <div class="note-text"><?php echo htmlspecialchars($note['note']); ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center py-3">No notes yet.</p>
    <?php endif; ?>
</div>

</div>

<?php include 'includes/footer.php'; ?>