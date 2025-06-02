<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = 'My Messages';
$current_user = getCurrentUser();

if (!$current_user) {
    redirect('login.php');
}

// Handle marking message as read
if ($_POST && isset($_POST['mark_read'])) {
    $message_id = (int) $_POST['message_id'];
    $stmt = $conn->prepare("UPDATE user_messages SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $message_id, $current_user['id']);
    $stmt->execute();
    $stmt->close();

    // Redirect to prevent resubmission
    redirect('messages.php');
}

// Handle new message submission
$contact_message = '';
$contact_error = '';

if ($_POST && isset($_POST['send_message'])) {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);

    if (empty($subject) || empty($message)) {
        $contact_error = 'Please fill in all fields.';
    } else {
        // Insert message into user_messages table
        $stmt = $conn->prepare("INSERT INTO user_messages (user_id, subject, message, is_read) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iss", $current_user['id'], $subject, $message);

        if ($stmt->execute()) {
            $contact_message = 'Your message has been sent to the admin successfully!';
            // Clear form
            $_POST = array();
        } else {
            $contact_error = 'Failed to send message. Please try again.';
        }
        $stmt->close();
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total message count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_messages WHERE user_id = ?");
$count_stmt->bind_param("i", $current_user['id']);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_messages = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_messages / $per_page);
$count_stmt->close();

// Get all user messages with pagination
$user_messages = [];
$stmt = $conn->prepare("
    SELECT id, subject, message, admin_reply, is_read, created_at, replied_at, is_read
    FROM user_messages 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $current_user['id'], $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_messages[] = $row;
}
$stmt->close();

// Count stats
$stats = [
    'total' => $total_messages,
    'pending' => 0,
    'replied' => 0,
    'unread_replies' => 0
];

$stats_stmt = $conn->prepare("
    SELECT 
        is_read,
        COUNT(*) as count,
        SUM(CASE WHEN admin_reply IS NOT NULL AND admin_reply != '' AND is_read = 0 THEN 1 ELSE 0 END) as unread_replies
    FROM user_messages 
    WHERE user_id = ? 
    GROUP BY is_read
");
$stats_stmt->bind_param("i", $current_user['id']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['is_read']] = $row['count'];
    $stats['unread_replies'] += $row['unread_replies'];
}
$stats_stmt->close();

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-6">
                        <i class="fas fa-comments text-primary"></i> My Messages
                    </h1>
                    <p class="text-muted">View your conversation history with admin</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <i class="fas fa-envelope fa-2x mb-2"></i>
                    <h4><?php echo $stats['total']; ?></h4>
                    <h6>Total Messages</h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center bg-warning text-dark">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h4><?php echo $stats['pending']; ?></h4>
                    <h6>Pending</h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <i class="fas fa-reply fa-2x mb-2"></i>
                    <h4><?php echo $stats['replied']; ?></h4>
                    <h6>Replied</h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <i class="fas fa-bell fa-2x mb-2"></i>
                    <h4><?php echo $stats['unread_replies']; ?></h4>
                    <h6>Unread Replies</h6>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Messages List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-inbox"></i> Message History
                            <?php if ($stats['unread_replies'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $stats['unread_replies']; ?> new replies</span>
                            <?php endif; ?>
                        </h5>
                        <?php if ($total_pages > 1): ?>
                            <small class="text-muted">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($user_messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No messages yet</h5>
                            <p class="text-muted">Start a conversation with the admin using the form on the right.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_messages as $msg): ?>
                            <div
                                class="message-thread mb-4 p-3 border rounded <?php echo (!empty($msg['admin_reply']) && $msg['is_read'] == 0) ? 'border-primary bg-light' : ''; ?>">
                                <!-- Message Header -->
                                <div class="message-header d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1 text-primary">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($msg['subject']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            Sent: <?php echo date('F j, Y \a\t g:i A', strtotime($msg['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span
                                            class="badge bg-<?php echo $msg['is_read'] == 'replied' ? 'success' : ($msg['is_read'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($msg['is_read']); ?>
                                        </span>
                                        <?php if (!empty($msg['admin_reply']) && $msg['is_read'] == 0): ?>
                                            <br>
                                            <span class="badge bg-danger mt-1">NEW REPLY</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- User's Original Message -->
                                <div class="user-message mb-3">
                                    <div class="p-3 bg-white border rounded">
                                        <div class="d-flex align-items-center mb-2">
                                            <div
                                                class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <strong class="text-primary">You</strong>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    </div>
                                </div>

                                <!-- Admin's Reply -->
                                <?php if (!empty($msg['admin_reply'])): ?>
                                    <div class="admin-reply">
                                        <div class="p-3 bg-success bg-opacity-10 border border-success rounded">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div
                                                        class="avatar-sm bg-success rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-user-shield text-white"></i>
                                                    </div>
                                                    <strong class="text-success">Admin</strong>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-reply"></i>
                                                    <?php echo date('F j, Y \a\t g:i A', strtotime($msg['replied_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                        </div>

                                        <!-- Mark as Read Button -->
                                        <?php if ($msg['is_read'] == 0): ?>
                                            <div class="mt-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check"></i> Mark as Read
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($msg['is_read'] == 'pending'): ?>
                                    <div class="pending-reply p-3 bg-warning bg-opacity-10 border border-warning rounded">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-hourglass-half text-warning me-2"></i>
                                            <em class="text-warning">Waiting for admin reply...</em>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Messages pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Send New Message -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-paper-plane"></i> Send New Message
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($contact_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $contact_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($contact_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $contact_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject" required>
                                <option value="">Select a topic...</option>
                                <option value="General Question" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Question') ? 'selected' : ''; ?>>General Question
                                </option>
                                <option value="Technical Issue" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Technical Issue') ? 'selected' : ''; ?>>Technical Issue</option>
                                <option value="Content Request" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Content Request') ? 'selected' : ''; ?>>Content Request</option>
                                <option value="Feedback" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feedback') ? 'selected' : ''; ?>>Feedback</option>
                                <option value="Account Issue" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Account Issue') ? 'selected' : ''; ?>>Account Issue</option>
                                <option value="Billing Question" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Billing Question') ? 'selected' : ''; ?>>Billing Question
                                </option>
                                <option value="Feature Request" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feature Request') ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" rows="6"
                                placeholder="Please describe your question or concern in detail..."
                                required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            <div class="form-text">Be as specific as possible to help us provide the best assistance.
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="send_message" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-question-circle"></i> Need Help?
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="faq.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-question"></i> View FAQ
                        </a>
                        <a href="help.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-life-ring"></i> Help Center
                        </a>
                        <a href="contact.php" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-phone"></i> Contact Info
                        </a>
                    </div>

                    <hr>

                    <div class="text-center">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i>
                            Average response time: <strong>24 hours</strong>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 32px;
        height: 32px;
    }

    .message-thread {
        transition: all 0.3s ease;
    }

    .message-thread:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .sticky-top {
        position: sticky;
        top: 20px;
        z-index: 10;
    }

    @media (max-width: 991.98px) {
        .sticky-top {
            position: relative;
            top: auto;
        }
    }

    .card {
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .badge {
        font-size: 0.75em;
    }

    .pagination .page-link {
        border-radius: 0.5rem;
        margin: 0 2px;
        border: 1px solid #dee2e6;
    }

    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (!alert.querySelector('.btn-close')) return;

            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Form validation
        const form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener('submit', function (e) {
                const subject = form.querySelector('select[name="subject"]').value;
                const message = form.querySelector('textarea[name="message"]').value.trim();

                if (!subject || !message) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }

                if (message.length < 10) {
                    e.preventDefault();
                    alert('Please provide a more detailed message (at least 10 characters).');
                    return false;
                }
            });
        }

        // Smooth scroll to new messages
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('new_message') === '1') {
            const firstMessage = document.querySelector('.message-thread');
            if (firstMessage) {
                firstMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>