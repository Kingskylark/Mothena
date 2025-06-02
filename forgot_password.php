<?php
require_once 'includes/config.php';
require 'includes/functions.php';

$page_title = 'Reset Password';
$error = '';
$message = '';
$step = 1; // Step 1: Email verification, Step 2: Security questions, Step 3: New password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Verify email and get security questions
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $step = 2;
                // Store user info in session for security
    
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $email;
            } else {
                $error = 'No account found with that email address.';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: Verify name and age
        if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } else {
            $name = sanitize($_POST['name'] ?? '');
            $age = sanitize($_POST['age'] ?? '');
            
            if (empty($name) || empty($age)) {
                $error = 'Please enter both your name and age.';
                $step = 2;
            } else {
                $stmt = $conn->prepare("SELECT name, age FROM users WHERE id = ?");
                $stmt->bind_param("i", $_SESSION['reset_user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // Verify name and age (case-insensitive for name, exact match for age)
                    if (strtolower(trim($name)) === strtolower(trim($user['name'])) && 
                        trim($age) === trim($user['age'])) {
                        $step = 3;
                    } else {
                        $error = 'Name or age is incorrect. Please try again.';
                        $step = 2;
                    }
                } else {
                    $error = 'User not found. Please start over.';
                    $step = 1;
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_email']);
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '3') {
        // Step 3: Update password
        if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } else {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in both password fields.';
                $step = 3;
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $step = 3;
            } elseif (strlen($new_password) < 8) {
                $error = 'Password must be at least 8 characters long.';
                $step = 3;
            } else {
                // Use the same password hashing method as your existing system
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
                
                if ($stmt->execute()) {
                    $message = 'Your password has been successfully updated! You can now <a href="login.php">login</a> with your new password.';
                    // Clear session data
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_email']);
                    $step = 4; // Success step
                } else {
                    $error = 'Failed to update password. Please try again.';
                    $step = 3;
                }
                $stmt->close();
            }
        }
    }
}

// No need to get security questions for step 2 since we're using existing columns

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-header text-center">
                    <h4><i class="fas fa-key"></i> Reset Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php elseif ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <!-- Step 1: Email Verification -->
                        <div class="mb-3">
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle"></i> Enter your email address to begin the password reset process.</small>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="1">
                            <div class="mb-3">
                                <label for="email" class="form-label">Your Email Address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Continue</button>
                            </div>
                        </form>
                        
                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Name and Age Verification -->
                        <div class="mb-3">
                            <div class="alert alert-info">
                                <small><i class="fas fa-shield-alt"></i> Please enter your name and age to verify your identity.</small>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="2">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Full Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Your Age</label>
                                <input type="number" name="age" class="form-control" min="1" max="120" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Verify Information</button>
                            </div>
                        </form>
                        
                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: New Password -->
                        <div class="mb-3">
                            <div class="alert alert-success">
                                <small><i class="fas fa-check-circle"></i> Identity verified! Now enter your new password.</small>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="3">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" required>
                                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">Update Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($step != 4): ?>
                        <div class="text-center mt-3">
                            <a href="login.php">Back to login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>