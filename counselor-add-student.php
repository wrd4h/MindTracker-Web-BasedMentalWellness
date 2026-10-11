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

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $matric_no = trim($_POST['matric_no']);
    $faculty = trim($_POST['faculty']);

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Name, email, and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, matric_no, faculty) VALUES (?, ?, ?, 'student', ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $hashed, $matric_no, $faculty);
            if ($stmt->execute()) {
                $message = "Student added successfully!";
            } else {
                $error = "Failed to add student.";
            }
        }
        $stmt->close();
    }
}

$page_title = 'Add Student';
include 'includes/header.php';
?>

<div class="container">

<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1><i class="bi bi-person-plus" style="color: #2E7D5B;"></i> Add New Student</h1>
        <p class="subtitle">Register a new student account.</p>
    </div>
    <a href="counselor-students.php" class="btn-primary-custom">
        <i class="bi bi-arrow-left"></i> Back to Students
    </a>
</div>

<?php if ($message): ?>
    <div class="message-success">✅ <?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message-error">❌ <?php echo $error; ?></div>
<?php endif; ?>

<div class="card-box">
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
            </div>
            <div class="col-md-3">
                <label class="form-label">Matric No</label>
                <input type="text" name="matric_no" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Faculty</label>
                <input type="text" name="faculty" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn-primary-custom mt-4">
            <i class="bi bi-save"></i> Add Student
        </button>
    </form>
</div>

</div>

<?php include 'includes/footer.php'; ?>