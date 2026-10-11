<?php
include 'config/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';
$today = date('Y-m-d');

// Get today's checkin
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) = ? ORDER BY checkin_id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$todayMood = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get wellness score for today
$wellness_score = null;
if ($todayMood) {
    $stmt = $conn->prepare("SELECT score FROM wellness_scores WHERE user_id = ? AND checkin_id = ?");
    $stmt->bind_param("ii", $user_id, $todayMood['checkin_id']);
    $stmt->execute();
    $score_row = $stmt->get_result()->fetch_assoc();
    $wellness_score = $score_row['score'] ?? null;
    $stmt->close();
}

// Get weekly checkins (last 7 days)
$stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND DATE(created_at) >= DATE_SUB(?, INTERVAL 6 DAY) ORDER BY created_at ASC");
$stmt->bind_param("ss", $user_id, $today);
$stmt->execute();
$weeklyMoods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total check-ins
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalCheckins = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get average stress
$stmt = $conn->prepare("SELECT AVG(stress_level) as avg_stress FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_stress = round($stmt->get_result()->fetch_assoc()['avg_stress'] ?? 0, 1);
$stmt->close();

// Get quote
$stmt = $conn->query("SELECT * FROM quotes ORDER BY RAND() LIMIT 1");
$quote = $stmt->fetch_assoc();
$stmt->close();

// Get recommendation from wellness_tips
$recommendation = null;
if ($todayMood) {
    $stmt = $conn->prepare("SELECT * FROM wellness_tips WHERE category = ? OR title = ? LIMIT 1");
    $stmt->bind_param("ss", $todayMood['mods'], $todayMood['mods']);
    $stmt->execute();
    $recommendation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MindTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f6f7f9; min-height: 100vh; }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 12px 0;
        }
        .navbar-brand {
            font-weight: 700;
            color: #6BAF7B !important;
            font-size: 22px;
        }
        .navbar-brand i { font-size: 28px; }
        .nav-link {
            color: #333 !important;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px !important;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-link:hover {
            background: #f0f4f8;
            color: #6BAF7B !important;
        }
        .nav-link.active {
            background: #6BAF7B;
            color: white !important;
        }

        .container { max-width: 1100px; }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 0 15px 0;
        }
        .welcome-section h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        .welcome-section h1 span { color: #6BAF7B; }
        .welcome-section .greeting { color: #888; font-size: 15px; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-card .stat-icon {
            font-size: 28px;
            color: #6BAF7B;
            background: #e8f5e9;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
        }
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        .stat-card .stat-label {
            font-size: 14px;
            color: #888;
        }

        .mood-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }
        .mood-card h5 {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .mood-card .big-emoji { font-size: 48px; }

        .btn-primary-custom {
            background: #6BAF7B;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary-custom:hover {
            background: #5a9d69;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(107, 175, 123, 0.3);
        }

        .quote-card {
            background: #e8f5e9;
            border-radius: 16px;
            padding: 20px 25px;
            text-align: center;
            border-left: 4px solid #6BAF7B;
        }
        .quote-card .quote-text {
            font-style: italic;
            font-size: 16px;
            color: #444;
        }
        .quote-card .quote-author {
            font-size: 13px;
            color: #888;
        }

        .recommendation-box {
            background: #d0edf2;
            border-radius: 16px;
            padding: 18px 25px;
            text-align: center;
            border-left: 4px solid #6BAF7B;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .recommendation-box .rec-text {
            font-size: 15px;
            color: #333;
        }
    </style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-heart-pulse"></i> MindTrack
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="checkin.php"><i class="bi bi-emoji-smile"></i> Check-In</a></li>
                <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container">

    <!-- Welcome -->
    <div class="welcome-section">
        <div>
            <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <span><?php echo htmlspecialchars($user_name); ?></span>! 🧠</h1>
            <p class="greeting">Your daily companion for better mental wellness.</p>
        </div>
        <div>
            <a href="checkin.php" class="btn-primary-custom">
                <i class="bi bi-plus-circle"></i> Daily Check-In
            </a>
        </div>
    </div>

    <!-- Quote -->
    <?php if ($quote): ?>
        <div class="quote-card mb-4">
            <p class="quote-text">"<?php echo htmlspecialchars($quote['quote_text'] ?? $quote['quote'] ?? ''); ?>"</p>
            <?php if (isset($quote['author']) && $quote['author']): ?>
                <p class="quote-author">— <?php echo htmlspecialchars($quote['author']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-emoji-smile"></i></div>
                <div class="stat-number"><?php echo $totalCheckins; ?></div>
                <div class="stat-label">Total Check-ins</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                <div class="stat-number"><?php echo $wellness_score ?? '-'; ?></div>
                <div class="stat-label">Today's Score</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-heart"></i></div>
                <div class="stat-number"><?php echo $avg_stress; ?></div>
                <div class="stat-label">Avg Stress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-number">
                    <?php
                    $streak = 0;
                    $current = date('Y-m-d');
                    $stmt = $conn->prepare("SELECT DATE(created_at) as log_date FROM checkins WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $dates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    foreach ($dates as $row) {
                        if ($row['log_date'] == $current) {
                            $streak++;
                            $current = date('Y-m-d', strtotime($current . ' -1 day'));
                        } else {
                            break;
                        }
                    }
                    echo $streak;
                    ?>
                </div>
                <div class="stat-label">Day Streak</div>
            </div>
        </div>
    </div>

    <!-- Today's Mood + Recommendation -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="mood-card">
                <h5><i class="bi bi-calendar-heart"></i> Today's Mood</h5>
                <?php if ($todayMood): ?>
                    <?php
                    $emojis = ['happy' => '😊', 'calm' => '😌', 'neutral' => '😐', 'sad' => '😢', 'very_sad' => '😭'];
                    ?>
                    <div class="big-emoji"><?php echo $emojis[$todayMood['mods']] ?? '😊'; ?></div>
                    <h4 class="mt-2"><?php echo ucfirst(str_replace('_', ' ', $todayMood['mods'])); ?></h4>
                    <p class="text-muted mb-0">Stress: <?php echo $todayMood['stress_level']; ?>/10</p>
                <?php else: ?>
                    <p class="text-muted">No mood logged today.</p>
                    <a href="checkin.php" class="btn-primary-custom">Log Mood</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <?php if ($recommendation): ?>
                <div class="recommendation-box">
                    <p style="font-size: 28px; margin: 0;">🌟</p>
                    <p class="rec-text mb-0"><strong>Tip:</strong> <?php echo htmlspecialchars($recommendation['description'] ?? $recommendation['title'] ?? ''); ?></p>
                </div>
            <?php else: ?>
                <div class="mood-card" style="height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                    <p style="font-size: 28px; margin: 0;">🧘</p>
                    <p class="text-muted">Log your mood to get personalized recommendations.</p>
                    <a href="checkin.php" class="btn-primary-custom" style="align-self: center;">Log Mood</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Weekly Chart -->
    <div class="mood-card mb-4">
        <h5><i class="bi bi-graph-up"></i> Weekly Mood Trend</h5>
        <?php if (count($weeklyMoods) > 0): ?>
            <canvas id="weeklyChart" style="max-height: 250px;"></canvas>
        <?php else: ?>
            <p class="text-muted text-center py-3">No data yet. Start logging your mood!</p>
        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($weeklyMoods) > 0): ?>
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const moodData = <?php echo json_encode($weeklyMoods); ?>;
    
    const labels = moodData.map(log => {
        const d = new Date(log.created_at);
        return d.toLocaleDateString('en-US', { weekday: 'short' });
    });
    
    const moodScores = moodData.map(log => {
        const map = { happy: 10, calm: 8, neutral: 6, sad: 4, very_sad: 2 };
        return map[log.mood] || 5;
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Mood Score',
                data: moodScores,
                borderColor: '#6BAF7B',
                backgroundColor: 'rgba(107, 175, 123, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#6BAF7B',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { min: 0, max: 10, ticks: { stepSize: 2 } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    <?php endif; ?>
});
</script>

</body>

</html>