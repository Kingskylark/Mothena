<?php
require_once 'includes/config.php';

$page_title = 'Welcome';

// Get some statistics for homepage
$stats = [];
if (isset($conn)) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $result ? $result->fetch_assoc()['total'] : 0;

    $result = $conn->query("SELECT COUNT(*) as total FROM content");
    $stats['total_content'] = $result ? $result->fetch_assoc()['total'] : 0;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center">
            <h1 class="display-4 mb-4">Welcome to Antenatal Care</h1>
            <p class="lead mb-4">Your personalized pregnancy journey companion. Get tailored advice, track your
                progress, and connect with healthcare professionals.</p>

            <?php if (!isLoggedIn()): ?>
                <div class="mb-4">
                    <a href="register.php" class="btn btn-primary btn-lg me-3">Get Started</a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <a href="dashboard.php" class="btn btn-primary btn-lg me-3">Go to Dashboard</a>
                    <a href="content.php" class="btn btn-outline-primary btn-lg">Browse Content</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row mb-5">
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Personalized Care</h5>
                    <p class="card-text">Get customized advice based on your trimester, age, and personal preferences.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Track Progress</h5>
                    <p class="card-text">Monitor your pregnancy milestones and receive timely reminders.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="fas fa-book fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Educational Content</h5>
                    <p class="card-text">Access a rich library of pregnancy-related articles, tips, and resources.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <?php if ($stats['total_users'] > 0): ?>
        <div class="row mb-5">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title">Join Our Community</h5>
                        <div class="row">
                            <div class="col-6">
                                <h3 class="text-primary"><?php echo $stats['total_users']; ?></h3>
                                <p class="mb-0">Registered Users</p>
                            </div>
                            <div class="col-6">
                                <h3 class="text-success"><?php echo $stats['total_content']; ?></h3>
                                <p class="mb-0">Articles & Tips</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Call to Action -->
    <?php if (!isLoggedIn()): ?>
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Ready to Start Your Journey?</h5>
                        <p class="card-text">Join thousands of expectant mothers who trust our platform for their pregnancy
                            care.</p>
                        <a href="register.php" class="btn btn-light btn-lg">Create Your Account</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>