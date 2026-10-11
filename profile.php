<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get quick stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_checkins = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(score) as avg_score FROM wellness_scores WHERE user_id = ? AND score > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_wellness = round($stmt->get_result()->fetch_assoc()['avg_score'] ?? 0);
$stmt->close();

$page_title = 'My Profile';
include 'includes/header.php';
?>

<div class="container">

<!-- Cover Photo -->
<div class="profile-cover"></div>

<!-- Profile Header -->
<div class="profile-header-card">
    <div class="profile-avatar-wrapper">
        <?php if (!empty($user['profile_photo']) && file_exists('uploads/profile/' . $user['profile_photo'])): ?>
            <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                 class="profile-avatar-img" alt="Profile Photo">
        <?php else: ?>
            <div class="profile-avatar-placeholder">
                <i class="bi bi-person"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="profile-header-info">
        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
        <p class="profile-role">
            <i class="bi bi-mortarboard"></i> Student
        </p>
        <p class="profile-email">
            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
        </p>
    </div>
    
    <a href="edit_profile.php" class="btn-primary-custom">
        <i class="bi bi-pencil"></i> Edit Profile
    </a>
</div>

<!-- Quick Stats -->
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
            <div class="stat-icon"><i class="bi bi-award"></i></div>
            <div class="stat-number" style="font-size: 18px;"><?php echo $user['matric_no'] ?? '-'; ?></div>
            <div class="stat-label">Matric No</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-number" style="font-size: 18px;"><?php echo $user['faculty'] ?? '-'; ?></div>
            <div class="stat-label">Faculty</div>
        </div>
    </div>
</div>

<!-- Personal Information -->
<div class="card-box">
    <h5><i class="bi bi-info-circle" style="color: #2E7D5B;"></i> Personal Information</h5>
    <div class="row mt-3">
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Full Name</label>
            <p><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Email</label>
            <p><strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Matric No</label>
            <p><strong><?php echo htmlspecialchars($user['matric_no'] ?? '-'); ?></strong></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Faculty</label>
            <p><strong><?php echo htmlspecialchars($user['faculty'] ?? '-'); ?></strong></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Role</label>
            <p><strong><?php echo ucfirst($user['role']); ?></strong></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">Member Since</label>
            <p><strong><?php echo date('d M Y', strtotime($user['created_at'])); ?></strong></p>
        </div>
    </div>
</div>

<div class="mt-3 text-center">
    <a href="dashboard.php" style="color: #888; text-decoration: none; font-size: 14px;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

</div>

<?php include 'includes/footer.php'; ?>