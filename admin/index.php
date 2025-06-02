<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Admin Dashboard';
$current_admin = getCurrentAdmin();
$stats = getDashboardStats();

include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($current_admin['full_name']); ?>!</h2>

    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <h3><?php echo $stats['total_users']; ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <h3><?php echo $stats['new_users_month']; ?></h3>
                <p>New This Month</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <h3><?php echo $stats['total_content']; ?></h3>
                <p>Total Content</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <h3><?php echo $stats['total_views']; ?></h3>
                <p>Total Views</p>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <canvas id="trimesterChart" style="max-height: 300px;"></canvas>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>