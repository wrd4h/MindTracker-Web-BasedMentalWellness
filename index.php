<?php
include 'config/db_config.php';

$page_title = 'Welcome';
include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="hero-badge">🧠 For University Students</span>
                <h1 class="hero-title">
                    Take a moment <br>for <span>yourself</span>.
                </h1>
                <p class="hero-subtitle">
                    Track your mood, understand your emotions, and build healthier daily habits — one gentle check-in at a time.
                </p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn-primary-custom">
                        <i class="bi bi-rocket-takeoff"></i> Get Started
                    </a>
                    <a href="auth/login.php" class="btn-secondary-custom">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <span class="hero-stat-emoji">😊</span>
                        <span>Mood Logged</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-emoji">📊</span>
                        <span>Wellness 82%</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
    <div class="hero-visual">
        <!-- Background circle -->
        <div class="hero-circle"></div>
        
        <!-- Illustration cards -->
        <div class="hero-card hero-card-1">
            <span class="hero-card-icon">😌</span>
            <div>
                <p class="hero-card-label">Feeling Calm</p>
                <small class="text-muted">Great job!</small>
            </div>
        </div>
        
        <div class="hero-card hero-card-2">
            <span class="hero-card-icon">😊</span>
            <div>
                <p class="hero-card-label">Feeling Happy</p>
                <small class="text-muted">Keep it up!</small>
            </div>
        </div>
        
        <div class="hero-card hero-card-3">
            <span class="hero-card-icon">📈</span>
            <div>
                <p class="hero-card-label">7-Day Streak</p>
                <small class="text-muted">You're amazing!</small>
            </div>
        </div>
        
        <!-- Floating emoji -->
        <div class="floating-emoji emoji-1">🌱</div>
        <div class="floating-emoji emoji-2">💚</div>
        <div class="floating-emoji emoji-3">🌸</div>
    </div>
</div>

<!-- Features Section -->
<div class="container">
    <div class="section-header text-center">
        <h2 class="section-title">Why MindTrack?</h2>
        <p class="section-subtitle">Simple tools to help you understand and care for your mental well-being.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">😊</div>
                <h4 class="feature-title">Track Your Mood</h4>
                <p class="feature-desc">Log your feelings daily in just a few taps. Simple, fast, and completely private.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h4 class="feature-title">Understand Yourself</h4>
                <p class="feature-desc">Gain insights about your mental wellness trends with easy-to-read charts and analytics.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">🌱</div>
                <h4 class="feature-title">Grow Every Day</h4>
                <p class="feature-desc">Build healthy habits and feel more like yourself with personalized wellness tips.</p>
            </div>
        </div>
    </div>

    <!-- Quote Section -->
    <div class="quote-section">
        <div class="quote-mark">"</div>
        <p class="quote-section-text">
            You don't have to be positive all the time. It's perfectly okay to feel sad, angry, annoyed, frustrated, scared, and anxious. Having feelings doesn't make you a negative person. It makes you human.
        </p>
        <p class="quote-section-author">— Lori Deschene</p>
    </div>
</div>

<!-- Footer -->
<footer class="landing-footer">
    <div class="container">
        <div class="text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> MindTrack — Built for university student wellbeing.</p>
        </div>
    </div>
</footer>

<?php include 'includes/footer.php'; ?>