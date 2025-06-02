<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Settings';
$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        $admin_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();

            // Check if password is MD5 (legacy) or bcrypt (new)
            $password_valid = false;
            if (strlen($admin['password']) === 32) {
                // Legacy MD5 password
                $password_valid = (md5($current_password) === $admin['password']);
            } else {
                // New bcrypt password
                $password_valid = password_verify($current_password, $admin['password']);
            }

            if ($password_valid) {
                // Update password with bcrypt
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $admin_id);

                if ($update_stmt->execute()) {
                    // Log the activity
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                    $log_stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, target_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
                    $action = 'password_change';
                    $target_type = 'admin';
                    $description = 'Admin changed password';
                    $log_stmt->bind_param("isssss", $admin_id, $action, $target_type, $description, $ip_address, $user_agent);
                    $log_stmt->execute();

                    $success = "Password changed successfully!";
                } else {
                    $error = "Failed to update password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } else {
            $error = "Admin not found.";
        }
    }
}

// Handle activity log settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_old_logs'])) {
    $days_to_keep = (int) ($_POST['days_to_keep'] ?? 30);

    if ($days_to_keep < 1) {
        $error = "Days to keep must be at least 1.";
    } else {
        $delete_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        $stmt = $conn->prepare("DELETE FROM admin_activity_log WHERE created_at < ?");
        $stmt->bind_param("s", $delete_date);

        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;

            // Log this cleanup activity
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log_stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, target_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
            $action = 'logs_cleanup';
            $target_type = 'system';
            $description = "Cleared {$deleted_count} old activity logs (older than {$days_to_keep} days)";
            $log_stmt->bind_param("isssss", $_SESSION['admin_id'], $action, $target_type, $description, $ip_address, $user_agent);
            $log_stmt->execute();

            $success = "Cleared {$deleted_count} old activity log entries.";
        } else {
            $error = "Failed to clear old logs.";
        }
    }
}

// Get recent activity logs for display
$logs_result = $conn->query("
    SELECT aal.*, au.username, au.full_name 
    FROM admin_activity_log aal 
    LEFT JOIN admin_users au ON aal.admin_id = au.id 
    ORDER BY aal.created_at DESC 
    LIMIT 10
");

// Get log statistics
$log_stats = $conn->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_week,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as last_month
    FROM admin_activity_log
")->fetch_assoc();

include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-cog"></i> Settings</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Change Password Section -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Activity Log Management -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Activity Log Management</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Log Statistics</h6>
                        <ul class="list-unstyled">
                            <li><strong>Total Logs:</strong> <?= number_format($log_stats['total_logs'] ?? 0) ?></li>
                            <li><strong>Last 7 Days:</strong> <?= number_format($log_stats['last_week'] ?? 0) ?></li>
                            <li><strong>Last 30 Days:</strong> <?= number_format($log_stats['last_month'] ?? 0) ?></li>
                        </ul>
                    </div>

                    <form method="POST" class="mb-3">
                        <label class="form-label">Clear Old Activity Logs</label>
                        <div class="input-group">
                            <input type="number" name="days_to_keep" class="form-control" value="30" min="1" max="365"
                                placeholder="Days to keep">
                            <button type="submit" name="clear_old_logs" class="btn btn-warning"
                                onclick="return confirm('Are you sure you want to clear old activity logs?')">
                                <i class="fas fa-trash"></i> Clear Old Logs
                            </button>
                        </div>
                        <div class="form-text">Keep logs from the last X days and delete older entries.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Logs -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Recent Admin Activity</h5>
        </div>
        <div class="card-body">
            <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></strong>
                                            <?php if (!empty($log['full_name'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($log['full_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-outline-primary">
                                            <?= htmlspecialchars($log['target_type']) ?>
                                            <?php if ($log['target_id']): ?>
                                                #<?= $log['target_id'] ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($log['description']) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '') ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-info-circle"></i> Showing last 10 activities only
                </div>
            <?php else: ?>
                <p class="text-muted">No activity logs found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>