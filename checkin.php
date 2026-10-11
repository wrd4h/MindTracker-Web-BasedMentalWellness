<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";
$today = date('Y-m-d');

// Check if user already logged today
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) = ? ORDER BY checkin_id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$existing_log = $result->fetch_assoc();
$stmt->close();

if (!$existing_log) {
    $existing_log = [];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($existing_log)) {
        $error = "You have already logged your mood today!";
    } else {
        $mood = $_POST['mood'];
        $stress_level = intval($_POST['stress_level']);
        $journal = trim($_POST['journal']);

        $valid_moods = ['happy', 'calm', 'neutral', 'sad', 'very_sad'];
        if (!in_array($mood, $valid_moods)) {
            $error = "Please select a valid mood.";
        } elseif ($stress_level < 1 || $stress_level > 10) {
            $error = "Stress level must be between 1 and 10.";
        } else {
            $stmt = $conn->prepare("INSERT INTO checkins (user_id, mood, stress_level, journal_entry, checkin_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiss", $user_id, $mood, $stress_level, $journal, $today);

            if ($stmt->execute()) {
                $checkin_id = $stmt->insert_id;
                $stmt->close();

                $mood_map = ['happy' => 100, 'calm' => 80, 'neutral' => 60, 'sad' => 40, 'very_sad' => 20];
                $mood_score = $mood_map[$mood] ?? 50;
                $stress_score = 100 - (($stress_level - 1) * 10);
                $wellness_score = round(($mood_score + $stress_score) / 2);

                $score_stmt = $conn->prepare("INSERT INTO wellness_scores (user_id, checkin_id, score, score_date) VALUES (?, ?, ?, ?)");
                $score_stmt->bind_param("iiis", $user_id, $checkin_id, $wellness_score, $today);

                if ($score_stmt->execute()) {
                    $message = "Mood logged successfully! Wellness Score: $wellness_score%";
                } else {
                    $message = "Mood logged, but wellness score not saved.";
                }
                $score_stmt->close();

                // Get personalized recommendation
                $personalized_rec = getPersonalizedRecommendation($mood, $stress_level);

                $stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) = ? ORDER BY checkin_id DESC LIMIT 1");
                $stmt->bind_param("is", $user_id, $today);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_log = $result->fetch_assoc() ?? [];
                $stmt->close();
            } else {
                $error = "Failed to save your mood. Please try again.";
                $stmt->close();
            }
        }
    }
}

// Get today's quote
$stmt = $conn->query("SELECT * FROM quotes ORDER BY RAND() LIMIT 1");
$quote = $stmt->fetch_assoc();
$stmt->close();

$user_name = $_SESSION['full_name'] ?? 'Student';
$emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];

$page_title = 'Daily Check-In';
include 'includes/header.php';
?>

<div class="checkin-wrapper">
    <div class="checkin-card">

        <div class="checkin-header-gradient">
            <div class="header-icon-large">
                <i class="bi bi-emoji-smile"></i>
            </div>
            <h2>Daily Check-In</h2>
            <p class="subtitle">How are you feeling today, <?php echo htmlspecialchars($user_name); ?>?</p>
        </div>

        <div class="checkin-body">

        <?php if ($quote): ?>
            <div class="quote-box" style="background: #F7F9F7; padding: 20px 25px; border-radius: 14px; margin-bottom: 25px; border-left: 4px solid #2E7D5B;">
                <p class="quote-text mb-1" style="font-style: italic; color: #444;">"<?php echo htmlspecialchars($quote['quote_text'] ?? $quote['quote'] ?? ''); ?>"</p>
                <?php if (isset($quote['author']) && $quote['author']): ?>
                    <p class="quote-author mb-0" style="font-size: 13px; color: #7A8B80;">— <?php echo htmlspecialchars($quote['author']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message-success">✅ <?php echo $message; ?></div>
            
            <?php if (isset($personalized_rec)): ?>
                <div class="recommendation-box mt-3" style="background: <?php echo $personalized_rec['color']; ?>; text-align: left; padding: 25px 30px;">
                    <h4 style="font-weight: 700; color: #1F1F1F; margin-bottom: 10px; font-size: 18px;">
                        <?php echo $personalized_rec['title']; ?>
                    </h4>
                    <p class="mb-0" style="color: #555; line-height: 1.7; font-size: 15px;">
                        <?php echo htmlspecialchars($personalized_rec['message']); ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($existing_log) && isset($existing_log['mood'])): ?>
            <div class="already-logged">
                <div class="emoji-big"><?php echo $emojis[$existing_log['mood']] ?? '😊'; ?></div>
                <h5 class="mt-2">You've already checked in today! ✅</h5>
                <p class="mb-1"><strong>Mood:</strong> <?php echo ucfirst(str_replace('_', ' ', $existing_log['mood'] ?? 'Unknown')); ?></p>
                <p class="mb-1"><strong>Stress Level:</strong> <?php echo $existing_log['stress_level'] ?? '-'; ?>/10</p>
                <?php if (!empty($existing_log['journal_entry'])): ?>
                    <p class="mb-0"><strong>Journal:</strong> <?php echo htmlspecialchars($existing_log['journal_entry']); ?></p>
                <?php endif; ?>
                <a href="dashboard.php" class="btn-submit mt-3" style="width: auto; padding: 12px 30px; display: inline-block; text-decoration: none;">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <label class="stress-label">How are you feeling today?</label>
                <div class="mood-grid-new">
                    <?php
                    $moods = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];
                    foreach ($moods as $value => $emoji):
                    ?>
                        <label class="mood-btn-new mood-<?php echo $value; ?>" id="mood_<?php echo $value; ?>">
                            <input type="radio" name="mood" value="<?php echo $value; ?>" required>
                            <span class="emoji"><?php echo $emoji; ?></span>
                            <span class="label"><?php echo ucfirst(str_replace('_', ' ', $value)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="stress-section">
                    <label class="stress-label">Stress Level</label>
                    <div class="stress-slider-wrapper">
                        <span class="stress-min">1 (Low)</span>
                        <input type="range" name="stress_level" class="stress-slider-new" min="1" max="10" value="5" id="stressSlider">
                        <span class="stress-max">10 (High)</span>
                    </div>
                    <div class="stress-display">
                        <span id="stressValue">5</span>
                        <small>/ 10</small>
                    </div>
                </div>

                <label class="stress-label" style="margin-top: 25px;">Daily Journal (Optional)</label>
                <textarea name="journal" class="journal-textarea" placeholder="How was your day? Any thoughts or feelings you'd like to share..."></textarea>

                <button type="submit" class="btn-submit mt-4">
                    <i class="bi bi-check2-circle"></i> Save My Mood
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-3 text-center">
            <a href="dashboard.php" style="color: #7A8B80; text-decoration: none; font-size: 14px;">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        </div> <!-- End checkin-body -->

    </div>
</div>

<script>
    var slider = document.getElementById('stressSlider');
    if (slider) {
        slider.addEventListener('input', function() {
            document.getElementById('stressValue').textContent = this.value;
        });
    }

    document.querySelectorAll('.mood-btn-new').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mood-btn-new').forEach(function(b) {
                b.classList.remove('selected');
            });
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });

    document.querySelectorAll('.mood-btn-new input[type="radio"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                this.closest('.mood-btn-new').classList.add('selected');
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>