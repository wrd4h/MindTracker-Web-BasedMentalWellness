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
$counselor_name = $_SESSION['full_name'] ?? 'Counsellor';

// Filter by student
$filter_student = intval($_GET['student'] ?? 0);

// Build query
$sql = "SELECT cn.*, 
        s.full_name as student_name, 
        s.matric_no as student_matric,
        s.profile_photo as student_photo,
        c.full_name as counselor_name
        FROM counselor_notes cn
        JOIN users s ON s.user_id = cn.student_id
        JOIN users c ON c.user_id = cn.counselor_id
        WHERE cn.counselor_id = ?";

$params = [$counselor_id];
$types = "i";

if ($filter_student > 0) {
    $sql .= " AND cn.student_id = ?";
    $params[] = $filter_student;
    $types .= "i";
}

$sql .= " ORDER BY cn.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get students who have notes (for filter)
$stmt = $conn->prepare("
    SELECT DISTINCT s.user_id, s.full_name 
    FROM counselor_notes cn
    JOIN users s ON s.user_id = cn.student_id
    WHERE cn.counselor_id = ?
    ORDER BY s.full_name
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$students_with_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Counselling Notes';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-journal-text" style="color: #6BAF7B;"></i> Counselling Notes</h1>
    <p class="subtitle">All your counselling notes for students, <?php echo htmlspecialchars($counselor_name); ?>.</p>
</div>

<!-- Filter -->
<?php if (count($students_with_notes) > 0): ?>
    <div class="card-box mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-8">
                <label class="form-label small text-muted">Filter by student:</label>
                <select name="student" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Students</option>
                    <?php foreach ($students_with_notes as $s): ?>
                        <option value="<?php echo $s['user_id']; ?>" 
                            <?php echo $filter_student === $s['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <a href="counselor-notes.php" class="btn-primary-custom w-100 mt-4" style="text-align: center;">
                    <i class="bi bi-arrow-clockwise"></i> Reset Filter
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Results Count -->
<p class="text-muted mb-3">
    Showing <strong><?php echo count($notes); ?></strong> note(s)
</p>

<!-- Notes List -->
<?php if (count($notes) > 0): ?>
    <div class="row g-3">
        <?php foreach ($notes as $note): ?>
            <div class="col-md-6">
                <div class="card-box h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if (!empty($note['student_photo']) && file_exists('uploads/profile/' . $note['student_photo'])): ?>
                            <img src="uploads/profile/<?php echo htmlspecialchars($note['student_photo']); ?>" 
                                 class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                 style="width: 45px; height: 45px;">
                                <i class="bi bi-person text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight: 600; color: #333; font-size: 15px;">
                                <?php echo htmlspecialchars($note['student_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: #888;">
                                <?php echo htmlspecialchars($note['student_matric'] ?? '-'); ?>
                            </div>
                        </div>
                        <a href="counselor-student-detail.php?id=<?php echo $note['student_id']; ?>" 
                           class="ms-auto btn-note" style="text-decoration: none;">
                            <i class="bi bi-eye"></i> View
                        </a>
                    </div>
                    
                    <div class="note-item" style="border-left-color: #6BAF7B; margin-bottom: 0;">
                        <div class="note-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
                    </div>
                    
                    <div class="note-header mt-2" style="font-size: 12px; color: #888;">
                        🕐 <?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">📝</div>
        <h4>No Notes Yet</h4>
        <p>You haven't written any counselling notes yet.</p>
        <a href="counselor-students.php" class="btn-primary-custom mt-3">
            <i class="bi bi-people"></i> Go to Students
        </a>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <a href="counselor-dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>