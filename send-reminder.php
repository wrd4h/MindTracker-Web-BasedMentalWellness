<?php
include 'config/db_config.php';
require_once 'config/email_config.php';

// Get all students
$stmt = $conn->query("
    SELECT u.user_id, u.full_name, u.email,
           (SELECT MAX(created_at) FROM checkins WHERE user_id = u.user_id) as last_checkin
    FROM users u
    WHERE u.role = 'student'
");
$students = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sent_count = 0;
$skip_count = 0;

foreach ($students as $student) {
    $last_checkin = $student['last_checkin'];
    
    // Calculate days since last check-in
    if ($last_checkin) {
        $days_since = floor((time() - strtotime($last_checkin)) / 86400);
    } else {
        $days_since = 999; // Never checked in
    }
    
    // Send reminder if 3+ days
    if ($days_since >= 3) {
        $subject = "We Miss You on MindTrack! 💚";
        $body = "
            <div style='font-family: Poppins, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f6f7f9;'>
                <div style='background: white; padding: 40px; border-radius: 16px;'>
                    <h1 style='color: #2E7D5B; text-align: center;'>🧠 MindTrack</h1>
                    <h2 style='color: #333;'>Hi {$student['full_name']}! 💚</h2>
                    <p style='color: #555; line-height: 1.6;'>
                        We noticed that you haven't logged your mood recently. We miss you!
                    </p>
                    <p style='color: #555; line-height: 1.6;'>
                        Taking just <strong>1 minute</strong> to check in with yourself can make a big difference. 
                        How are you feeling today?
                    </p>
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='http://localhost/mental_wellness/checkin.php' 
                           style='display: inline-block; padding: 14px 35px; background: #2E7D5B; color: white; text-decoration: none; border-radius: 10px; font-weight: 600;'>
                            Check-In Now
                        </a>
                    </div>
                    <p style='margin-top: 30px; color: #888; font-size: 13px; text-align: center;'>
                        This is an automated email. Please do not reply.
                    </p>
                </div>
            </div>
        ";
        
        if (sendEmail($student['email'], $student['full_name'], $subject, $body)) {
            $sent_count++;
        }
    } else {
        $skip_count++;
    }
}

echo "<div style='font-family: Poppins, sans-serif; padding: 40px; max-width: 600px; margin: 0 auto;'>";
echo "<h2 style='color: #2E7D5B;'>✅ Email Reminder Sent!</h2>";
echo "<p>Reminder sent to <strong>$sent_count</strong> student(s).</p>";
echo "<p>Skipped <strong>$skip_count</strong> student(s) who checked in recently.</p>";
echo "<p style='margin-top: 20px;'><a href='counselor-dashboard.php' style='color: #2E7D5B;'>← Back to Dashboard</a></p>";
echo "</div>";
?>