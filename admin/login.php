<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (isAdminLoggedIn()) {
    redirect('index.php');
}

$page_title = 'Admin Login';
$error = '';

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $result = verifyAdminLogin($username, $password);

    if ($result['success']) {
        $_SESSION['admin_id'] = $result['admin']['id'];

        // Log the admin login activity
        logAdminActivity(
            $result['admin']['id'],
            'admin_login',
            'admin',
            $result['admin']['id'],
            'Admin user logged in successfully',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        redirect('index.php');
    } else {
        // Optionally log failed login attempts too
        if (isset($result['admin_id'])) {
            logAdminActivity(
                $result['admin_id'],
                'admin_login_failed',
                'admin',
                $result['admin_id'],
                'Failed login attempt for admin user',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        }

        $error = $result['message'];
    }
}

include '../includes/admin_header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-header text-center">
                    <h4><i class="fas fa-user-shield"></i> Admin Login</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-dark btn-lg">Login</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>