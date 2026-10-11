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

$student_id = intval($_GET['id'] ?? 0);

if ($student_id > 0) {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: counselor-students.php?deleted=1");
exit();
?>