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

// Handle export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    header('Content-Type: text/csv; charset=utf-8');
    
    if ($type === 'students') {
        header('Content-Disposition: attachment; filename="students_report_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Student ID', 'Full Name', 'Email', 'Matric No', 'Faculty', 'Total Check-ins', 'Avg Wellness', 'Last Check-in']);
        
        $query = "SELECT u.user_id, u.full_name, u.email, u.matric_no, u.faculty,
                  (SELECT COUNT(*) FROM checkins WHERE user_id = u.user_id) as total_checkins,
                  (SELECT AVG(score) FROM wellness_scores WHERE user_id = u.user_id AND score > 0) as avg_wellness,
                  (SELECT MAX(created_at) FROM checkins WHERE user_id = u.user_id) as last_checkin
                  FROM users u WHERE u.role = 'student' ORDER BY u.full_name ASC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['user_id'],
                $row['full_name'],
                $row['email'],
                $row['matric_no'] ?? '-',
                $row['faculty'] ?? '-',
                $row['total_checkins'],
                $row['avg_wellness'] ? round($row['avg_wellness']) . '%' : '-',
                $row['last_checkin'] ? date('d M Y', strtotime($row['last_checkin'])) : 'Never'
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    if ($type === 'moods') {
        header('Content-Disposition: attachment; filename="mood_data_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Check-in ID', 'Student Name', 'Matric No', 'Mood', 'Stress Level', 'Journal', 'Date']);
        
        $query = "SELECT c.checkin_id, u.full_name, u.matric_no, c.mood, c.stress_level, c.journal_entry, c.created_at
                  FROM checkins c
                  JOIN users u ON u.user_id = c.user_id
                  ORDER BY c.created_at DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['checkin_id'],
                $row['full_name'],
                $row['matric_no'] ?? '-',
                ucfirst(str_replace('_', ' ', $row['mood'])),
                $row['stress_level'],
                $row['journal_entry'] ?? '',
                date('d M Y, h:i A', strtotime($row['created_at']))
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    if ($type === 'wellness') {
        header('Content-Disposition: attachment; filename="wellness_scores_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Score ID', 'Student Name', 'Matric No', 'Wellness Score', 'Date']);
        
        $query = "SELECT ws.score_id, u.full_name, u.matric_no, ws.score, ws.score_date
                  FROM wellness_scores ws
                  JOIN users u ON u.user_id = ws.user_id
                  WHERE ws.score > 0
                  ORDER BY ws.score_date DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['score_id'],
                $row['full_name'],
                $row['matric_no'] ?? '-',
                $row['score'] . '%',
                date('d M Y', strtotime($row['score_date']))
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    if ($type === 'notes') {
        header('Content-Disposition: attachment; filename="counseling_notes_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Note ID', 'Student Name', 'Matric No', 'Note', 'Date']);
        
        $stmt = $conn->prepare("SELECT cn.note_id, s.full_name, s.matric_no, cn.note, cn.created_at
                                FROM counselor_notes cn
                                JOIN users s ON s.user_id = cn.student_id
                                WHERE cn.counselor_id = ?
                                ORDER BY cn.created_at DESC");
        $stmt->bind_param("i", $counselor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['note_id'],
                $row['full_name'],
                $row['matric_no'] ?? '-',
                $row['note'],
                date('d M Y, h:i A', strtotime($row['created_at']))
            ]);
        }
        
        $stmt->close();
        fclose($output);
        exit();
    }
}

// Get stats for display
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM checkins");
$total_checkins = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM wellness_scores WHERE score > 0");
$total_scores = $stmt->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM counselor_notes WHERE counselor_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$total_notes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$page_title = 'Export Reports';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-download" style="color: #2E7D5B;"></i> Export Reports</h1>
    <p class="subtitle">Download reports in CSV format for analysis, <?php echo htmlspecialchars($counselor_name); ?>.</p>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card-box h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon" style="margin: 0;">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h5 class="mb-0">Students Report</h5>
                    <small class="text-muted"><?php echo $total_students; ?> students</small>
                </div>
            </div>
            <p class="text-muted" style="font-size: 14px;">
                Contains list of all students with their check-in count, average wellness score, and last check-in date.
            </p>
            <a href="?export=students" class="btn-primary-custom w-100 d-block text-center" style="text-decoration: none;">
                <i class="bi bi-file-earmark-arrow-down"></i> Download CSV
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-box h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon" style="margin: 0;">
                    <i class="bi bi-emoji-smile"></i>
                </div>
                <div>
                    <h5 class="mb-0">Mood Data Report</h5>
                    <small class="text-muted"><?php echo $total_checkins; ?> check-ins</small>
                </div>
            </div>
            <p class="text-muted" style="font-size: 14px;">
                Contains all mood check-ins from students including mood type, stress level, journal entries, and date.
            </p>
            <a href="?export=moods" class="btn-primary-custom w-100 d-block text-center" style="text-decoration: none;">
                <i class="bi bi-file-earmark-arrow-down"></i> Download CSV
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-box h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon" style="margin: 0;">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <div>
                    <h5 class="mb-0">Wellness Scores Report</h5>
                    <small class="text-muted"><?php echo $total_scores; ?> records</small>
                </div>
            </div>
            <p class="text-muted" style="font-size: 14px;">
                Contains all wellness scores calculated from student check-ins with dates for trend analysis.
            </p>
            <a href="?export=wellness" class="btn-primary-custom w-100 d-block text-center" style="text-decoration: none;">
                <i class="bi bi-file-earmark-arrow-down"></i> Download CSV
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-box h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon" style="margin: 0;">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div>
                    <h5 class="mb-0">Counselling Notes</h5>
                    <small class="text-muted"><?php echo $total_notes; ?> notes</small>
                </div>
            </div>
            <p class="text-muted" style="font-size: 14px;">
                Contains all counselling notes you have written for students, including dates.
            </p>
            <a href="?export=notes" class="btn-primary-custom w-100 d-block text-center" style="text-decoration: none;">
                <i class="bi bi-file-earmark-arrow-down"></i> Download CSV
            </a>
        </div>
    </div>
</div>

<div class="card-box mt-4">
    <h5><i class="bi bi-info-circle" style="color: #2E7D5B;"></i> How to Use</h5>
    <ul class="mb-0" style="color: #666; font-size: 14px; line-height: 1.8;">
        <li>Click any <strong>Download CSV</strong> button to download the report.</li>
        <li>CSV files can be opened in <strong>Microsoft Excel</strong> or <strong>Google Sheets</strong>.</li>
        <li>Use these reports for <strong>evidence-based decisions</strong> and identifying trends.</li>
        <li>Reports are generated in real-time with the latest data.</li>
    </ul>
</div>

<div class="mt-3 text-center">
    <a href="counselor-dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>