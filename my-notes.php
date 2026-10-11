<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get notes from counsellor
$stmt = $conn->prepare("
    SELECT cn.*, u.full_name as counselor_name 
    FROM counselor_notes cn 
    JOIN users u ON u.user_id = cn.counselor_id 
    WHERE cn.student_id = ? 
    ORDER BY cn.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Notes';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-journal-text" style="color: #2E7D5B;"></i> Notes from Counsellor</h1>
    <p class="subtitle">View notes and advice from your counsellor.</p>
</div>

<?php if (count($notes) > 0): ?>
    <?php foreach ($notes as $note): ?>
        <div class="card-box mb-3">
            <div class="d-flex justify-content-between mb-2">
                <strong><?php echo htmlspecialchars($note['counselor_name']); ?></strong>
                <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?></small>
            </div>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">📝</div>
        <h4>No Notes Yet</h4>
        <p>Your counsellor has not written any notes for you.</p>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>