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

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_faculty = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';

// Build query
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM checkins WHERE user_id = u.user_id) as total_checkins,
        (SELECT AVG(score) FROM wellness_scores WHERE user_id = u.user_id) as avg_wellness,
        (SELECT MAX(created_at) FROM checkins WHERE user_id = u.user_id) as last_checkin
        FROM users u 
        WHERE u.role = 'student'";

$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.matric_no LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($filter_faculty)) {
    $sql .= " AND u.faculty = ?";
    $params[] = $filter_faculty;
    $types .= "s";
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique faculties
$stmt = $conn->query("SELECT DISTINCT faculty FROM users WHERE role = 'student' AND faculty IS NOT NULL AND faculty != '' ORDER BY faculty");
$faculties = $stmt->fetch_all(MYSQLI_ASSOC);

$page_title = 'Students';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1><i class="bi bi-people" style="color: #2E7D5B;"></i> Students</h1>
        <p class="subtitle">Monitor and manage all registered students.</p>
    </div>
    <a href="counselor-add-student.php" class="btn-primary-custom">
        <i class="bi bi-person-plus"></i> Add Student
    </a>
</div>
<!-- Search & Filter -->
<div class="card-box mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control" 
                   placeholder="Search by name, email, or matric no..." 
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-4">
            <select name="faculty" class="form-select">
                <option value="">All Faculties</option>
                <?php foreach ($faculties as $fac): ?>
                    <option value="<?php echo htmlspecialchars($fac['faculty']); ?>"
                        <?php echo $filter_faculty === $fac['faculty'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($fac['faculty']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn-primary-custom w-100">
                <i class="bi bi-search"></i> Search
            </button>
        </div>
    </form>
</div>

<!-- Results Count -->
<p class="text-muted mb-3">
    Showing <strong><?php echo count($students); ?></strong> student(s)
    <?php if (!empty($search)): ?>
        for "<strong><?php echo htmlspecialchars($search); ?></strong>"
    <?php endif; ?>
</p>

<!-- Students List -->
<?php if (count($students) > 0): ?>
    <div class="card-box">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Student</th>
                        <th>Matric No</th>
                        <th>Faculty</th>
                        <th class="text-center">Check-ins</th>
                        <th class="text-center">Avg Wellness</th>
                        <th class="text-center">Last Check-in</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $avg = $student['avg_wellness'] ? round($student['avg_wellness']) : 0;
                        $avg_color = $avg >= 70 ? '#2ecc71' : ($avg >= 40 ? '#f1c40f' : '#e74c3c');
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($student['profile_photo']) && file_exists('uploads/profile/' . $student['profile_photo'])): ?>
                                        <img src="uploads/profile/<?php echo htmlspecialchars($student['profile_photo']); ?>" 
                                             class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($student['matric_no'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($student['faculty'] ?? '-'); ?></td>
                            <td class="text-center"><strong><?php echo $student['total_checkins']; ?></strong></td>
                            <td class="text-center">
                                <?php if ($student['avg_wellness']): ?>
                                    <span style="color: <?php echo $avg_color; ?>; font-weight: 700;"><?php echo $avg; ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($student['last_checkin']): ?>
                                    <span style="font-size: 13px;"><?php echo date('d M Y', strtotime($student['last_checkin'])); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1">
    <a href="counselor-student-detail.php?id=<?php echo $student['user_id']; ?>" 
       class="btn-note">
        <i class="bi bi-eye"></i> View
    </a>
    <a href="counselor-delete-student.php?id=<?php echo $student['user_id']; ?>" 
       class="btn-note" style="background: #e74c3c;"
       onclick="return confirm('Delete this student?')">
        <i class="bi bi-trash"></i> Delete
    </a>
</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">👥</div>
        <h4>No Students Found</h4>
        <p>No students match your search criteria.</p>
        <a href="counselor-students.php" class="btn-primary-custom mt-3">Clear Search</a>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <a href="counselor-dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>