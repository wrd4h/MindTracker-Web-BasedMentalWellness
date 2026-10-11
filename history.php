<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';

// Get filter month
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';

// Build query
$sql = "SELECT * FROM checkins WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($filter_month)) {
    $sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];
$mood_colors = ['happy' => '#d4edda', 'calm' => '#d1ecf1', 'neutral' => '#fff3cd', 'sad' => '#f8d7da', 'very_sad' => '#f5c6cb'];

$page_title = 'Mood History';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-clock-history" style="color: #6BAF7B;"></i> Mood History</h1>
    <p class="subtitle">Track your emotional journey over time, <?php echo htmlspecialchars($user_name); ?>.</p>
</div>

<!-- Filter by Month -->
<div class="card-box mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label small text-muted">Filter by Month</label>
            <input type="month" name="month" 
           value="<?php echo htmlspecialchars($filter_month); ?>"
           style="width: 100%; padding: 16px 20px; font-size: 16px; height: 56px; border: 2px solid #E8EDE8; border-radius: 12px; background: #F7F9F7; box-sizing: border-box;">
        </div>
        <div class="col-md-2">
            <button type="submit" 
            style="width: 100%; padding: 16px 20px; font-size: 14px; height: 56px; background: linear-gradient(135deg, #2E7D5B, #256B4C); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">
        <i class="bi bi-funnel"></i> Apply
            </button>
        </div>
        <div class="col-md-2">
            <a href="history.php" 
           style="width: 100%; padding: 16px 20px; font-size: 14px; height: 56px; background: white; color: #2E7D5B; border: 2px solid #2E7D5B; border-radius: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
           <i class="bi bi-arrow-clockwise"></i> Reset
            </a>
         </div>
    </form>
</div>

<?php if (count($checkins) > 0): ?>
    <?php foreach ($checkins as $checkin): ?>
        <?php
        $mood = isset($checkin['mods']) ? $checkin['mods'] : 'neutral';
        $color = isset($mood_colors[$mood]) ? $mood_colors[$mood] : '#f8f9fa';
        $date = date('d M Y, h:i A', strtotime($checkin['created_at'] ?? 'now'));
        $stress = isset($checkin['stress_level']) ? $checkin['stress_level'] : '-';
        $journal = isset($checkin['journal_entry']) ? $checkin['journal_entry'] : '';
        $emoji = isset($emojis[$mood]) ? $emojis[$mood] : '😊';
        $mood_label = ucfirst(str_replace('_', ' ', $mood));
        ?>
        <div class="history-card" style="border-left-color: <?php echo $color; ?>;">
            <div class="left">
                <div class="emoji"><?php echo $emoji; ?></div>
                <div class="info">
                    <div class="date"><?php echo $date; ?></div>
                    <div class="mood-label"><?php echo $mood_label; ?></div>
                    <?php if (!empty($journal)): ?>
                        <div class="journal-preview">📝 <?php echo htmlspecialchars($journal); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="right">
                <div class="stress">😰 Stress: <strong><?php echo $stress; ?>/10</strong></div>
                <?php
                $score = null;
                if (isset($checkin['checkin_id'])) {
                    $score_stmt = $conn->prepare("SELECT score FROM wellness_scores WHERE checkin_id = ?");
                    $score_stmt->bind_param("i", $checkin['checkin_id']);
                    $score_stmt->execute();
                    $score_row = $score_stmt->get_result()->fetch_assoc();
                    $score_stmt->close();
                    if ($score_row) $score = $score_row['score'];
                }
                ?>
                <?php if ($score !== null): ?>
                    <div class="wellness-score">🏆 <?php echo $score; ?>%</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">📭</div>
        <h4>No Mood Logs Yet</h4>
        <p>Start your wellness journey by logging your first mood!</p>
        <a href="checkin.php" class="btn-primary-custom mt-3">Log Your Mood</a>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div> <!-- End container -->

<?php include 'includes/footer.php'; ?>