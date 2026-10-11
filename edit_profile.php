<?php

include 'config/db_config.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// ========================================
// Get current user information
// ========================================

$stmt = $conn->prepare(
    "SELECT user_id, full_name, email, matric_no, faculty, profile_photo
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ========================================
// Update profile
// ========================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);
    $matric_no = trim($_POST['matric_no']);
    $faculty = trim($_POST['faculty']);

    // ========================================
    // Update text information
    // ========================================

    $stmt = $conn->prepare(
        "UPDATE users
         SET full_name = ?, matric_no = ?, faculty = ?
         WHERE user_id = ?"
    );

    $stmt->bind_param(
        "sssi",
        $full_name,
        $matric_no,
        $faculty,
        $user_id
    );

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $message = "Profile updated successfully! 💚";
    } else {
        $error = "Something went wrong while updating your profile.";
    }

    $stmt->close();

    // ========================================
    // Profile photo upload
    // ========================================

    if (
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] != UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES['profile_photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024;

        if (!in_array($file['type'], $allowed_types)) {
            $error = "Only JPG and PNG images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $error = "Image size must be 2MB or less.";
        } else {

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
            $upload_directory = 'uploads/profile/';

            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0777, true);
            }

            $upload_path = $upload_directory . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {

                if (!empty($user['profile_photo']) && file_exists('uploads/profile/' . $user['profile_photo'])) {
                    unlink('uploads/profile/' . $user['profile_photo']);
                }

                $photo_stmt = $conn->prepare(
                    "UPDATE users SET profile_photo = ? WHERE user_id = ?"
                );
                $photo_stmt->bind_param("si", $new_filename, $user_id);

                if ($photo_stmt->execute()) {
                    $message = "Profile and photo updated successfully! 💚";
                    $user['profile_photo'] = $new_filename;
                } else {
                    $error = "Photo uploaded, but database update failed.";
                }
                $photo_stmt->close();

            } else {
                $error = "Failed to move the uploaded image.";
            }
        }
    }

    // Refresh user data
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, matric_no, faculty, profile_photo
         FROM users
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - MindTrack</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .message-success { color: #27ae60; background: #eafaf1; padding: 10px; border-radius: 5px; border-left: 4px solid #27ae60; }
        .message-error { color: #e74c3c; background: #fdedec; padding: 10px; border-radius: 5px; border-left: 4px solid #e74c3c; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        input[type="file"] { margin-top: 5px; }
        button {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 16px;
            width: 100%;
        }
        button:hover { background: #2980b9; }
        .profile-photo-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 10px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #3498db;
        }
        .no-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #bdc3c7;
            border: 3px dashed #bdc3c7;
        }
        .photo-info { font-size: 14px; color: #666; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .disabled-input { background: #e9ecef; cursor: not-allowed; }
        small { color: #888; font-size: 12px; }
    </style>
</head>

<body>

<div class="container">

    <h1>🧠 MindTrack</h1>
    <h2>Edit Profile ✏️</h2>

    <?php if ($message != ""): ?>
        <div class="message-success">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error != ""): ?>
        <div class="message-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h3>📸 Profile Photo</h3>

    <div class="profile-photo-container">
        <?php if (!empty($user['profile_photo']) && file_exists('uploads/profile/' . $user['profile_photo'])): ?>
            <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo" class="profile-photo">
            <div>
                <p><strong>Current photo:</strong></p>
                <p class="photo-info">📁 <?php echo htmlspecialchars($user['profile_photo']); ?></p>
            </div>
        <?php else: ?>
            <div class="no-photo">👤</div>
            <div>
                <p>No profile photo uploaded.</p>
                <p class="photo-info">Upload a photo below</p>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data">

        <label>Choose Profile Photo</label>
        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png">
        <small>Max 2MB, JPG/PNG only</small>

        <label>Full Name <span style="color:red;">*</span></label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>

        <label>Email</label>
        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="disabled-input" disabled>
        <small>Email cannot be changed</small>

        <label>Matric No</label>
        <input type="text" name="matric_no" value="<?php echo htmlspecialchars($user['matric_no'] ?? ''); ?>">

        <label>Faculty</label>
        <input type="text" name="faculty" value="<?php echo htmlspecialchars($user['faculty'] ?? ''); ?>">

        <button type="submit">💾 Save Changes</button>

    </form>

    <br>
    <a href="profile.php">← Back to Profile</a>

</div>

</body>

</html>