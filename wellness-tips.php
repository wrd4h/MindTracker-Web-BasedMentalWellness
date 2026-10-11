<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';

$stmt = $conn->query("SELECT * FROM wellness_tips ORDER BY tip_id ASC");
$tips = $stmt->fetch_all(MYSQLI_ASSOC);

$categories = [];
foreach ($tips as $tip) {
    if (!empty($tip['category']) && !in_array($tip['category'], $categories)) {
        $categories[] = $tip['category'];
    }
}

$selected_category = $_GET['category'] ?? 'all';

$filtered_tips = $tips;
if ($selected_category !== 'all') {
    $filtered_tips = array_filter($tips, function($tip) use ($selected_category) {
        return $tip['category'] === $selected_category;
    });
}

$page_title = 'Wellness Tips';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header">
    <h1><i class="bi bi-lightbulb" style="color: #6BAF7B;"></i> Wellness Tips</h1>
    <p class="subtitle">Take care of your mind and body, <?php echo htmlspecialchars($user_name); ?>.</p>
</div>

<?php if (count($categories) > 0): ?>
    <div class="filter-bar">
        <a href="wellness-tips.php" class="filter-btn <?php echo $selected_category === 'all' ? 'active' : ''; ?>">
            <i class="bi bi-grid"></i> All Tips
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="wellness-tips.php?category=<?php echo urlencode($cat); ?>" 
               class="filter-btn <?php echo $selected_category === $cat ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat); ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (count($filtered_tips) > 0): ?>
    <div class="row g-4">
        <?php foreach ($filtered_tips as $tip): ?>
            <div class="col-md-6 col-lg-4">
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="bi <?php echo htmlspecialchars($tip['icon'] ?? 'bi-lightbulb'); ?>"></i>
                    </div>
                    <?php if (!empty($tip['category'])): ?>
                        <span class="tip-category"><?php echo htmlspecialchars($tip['category']); ?></span>
                    <?php endif; ?>
                    <h5><?php echo htmlspecialchars($tip['title']); ?></h5>
                    <p><?php echo htmlspecialchars($tip['description']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="big-icon">💡</div>
        <h4>No Tips Available</h4>
        <p>Wellness tips will appear here soon.</p>
    </div>
<?php endif; ?>

<div class="reminder-banner">
    <h5>💚 Remember</h5>
    <p>You are not alone. It's okay to seek help. Reaching out to a counsellor or trusted friend is a sign of strength, not weakness.</p>
</div>

<div class="mt-4 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div> <!-- End container -->

<?php include 'includes/footer.php'; ?>