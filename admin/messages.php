<?php

require_once '../includes/config.php';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = (int) $_POST['message_id'];
    $admin_reply = mysqli_real_escape_string($conn, trim($_POST['admin_reply']));

    if (!empty($admin_reply)) {
        $query = "UPDATE user_messages 
                  SET admin_reply = '$admin_reply', 
                      replied_at = NOW(), 
                      replied_by = " . $_SESSION['admin_id'] . ", 
                      is_read = TRUE 
                  WHERE id = $message_id";

        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = 'Reply sent successfully!';
        } else {
            $_SESSION['error'] = 'Error sending reply: ' . mysqli_error($conn);
        }

        header('Location: messages.php');
        exit();
    }
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $message_id = (int) $_GET['mark_read'];
    $query = "UPDATE user_messages SET is_read = TRUE WHERE id = $message_id";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = 'Message marked as read!';
    } else {
        $_SESSION['error'] = 'Error marking message as read: ' . mysqli_error($conn);
    }

    header('Location: messages.php');
    exit();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$whereClause = "WHERE 1=1";

if ($filter === 'unread') {
    $whereClause .= " AND um.is_read = FALSE";
} elseif ($filter === 'replied') {
    $whereClause .= " AND um.admin_reply IS NOT NULL";
} elseif ($filter === 'unreplied') {
    $whereClause .= " AND um.admin_reply IS NULL";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $whereClause .= " AND (um.subject LIKE '%$search%' OR um.message LIKE '%$search%' OR u.username LIKE '%$search%')";
}

// Get messages with user info
$query = "SELECT 
            um.*,
            u.name,
            u.email,
            au.username as replied_by_username
          FROM user_messages um
          JOIN users u ON um.user_id = u.id
          LEFT JOIN admin_users au ON um.replied_by = au.id
          $whereClause
          ORDER BY um.created_at DESC";

$result = mysqli_query($conn, $query);
$messages = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
}

// Get counts for badges
$counts = [];

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM user_messages");
$counts['total'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

$result = mysqli_query($conn, "SELECT COUNT(*) as unread FROM user_messages WHERE is_read = FALSE");
$counts['unread'] = $result ? mysqli_fetch_assoc($result)['unread'] : 0;

$result = mysqli_query($conn, "SELECT COUNT(*) as replied FROM user_messages WHERE admin_reply IS NOT NULL");
$counts['replied'] = $result ? mysqli_fetch_assoc($result)['replied'] : 0;

$result = mysqli_query($conn, "SELECT COUNT(*) as unreplied FROM user_messages WHERE admin_reply IS NULL");
$counts['unreplied'] = $result ? mysqli_fetch_assoc($result)['unreplied'] : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .message-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
        }

        .message-card.unread {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }

        .message-card.replied {
            border-left-color: #198754;
        }

        .message-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .message-content {
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }

        .message-content.expanded {
            max-height: none;
        }

        .expand-btn {
            background: linear-gradient(transparent, white);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 10px 0 5px;
        }

        .reply-form {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .filter-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 8px 16px;
            margin-right: 5px;
            border-radius: 20px;
            background-color: #f8f9fa;
        }

        .filter-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>

<body class="bg-light">
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">


            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div
                    class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-envelope me-2"></i>User Messages</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <ul class="nav filter-tabs">
                            <li class="nav-item">
                                <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">
                                    All <span class="badge bg-secondary"><?= $counts['total'] ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $filter === 'unread' ? 'active' : '' ?>" href="?filter=unread">
                                    Unread <span class="badge bg-danger"><?= $counts['unread'] ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $filter === 'unreplied' ? 'active' : '' ?>"
                                    href="?filter=unreplied">
                                    Unreplied <span class="badge bg-warning"><?= $counts['unreplied'] ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $filter === 'replied' ? 'active' : '' ?>" href="?filter=replied">
                                    Replied <span class="badge bg-success"><?= $counts['replied'] ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="text" class="form-control" name="search" placeholder="Search messages..."
                                value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary ms-2" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Messages List -->
                <?php if (empty($messages)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No messages found</h5>
                            <p class="text-muted">There are no messages matching your current filter.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div
                            class="card message-card mb-3 <?= !$message['is_read'] ? 'unread' : '' ?> <?= $message['admin_reply'] ? 'replied' : '' ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-1">
                                                <?= htmlspecialchars($message['subject']) ?>
                                                <?php if (!$message['is_read']): ?>
                                                    <span class="badge bg-danger ms-2">New</span>
                                                <?php endif; ?>
                                                <?php if ($message['admin_reply']): ?>
                                                    <span class="badge bg-success ms-2">Replied</span>
                                                <?php endif; ?>
                                            </h5>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-user me-1"></i>
                                            <strong><?= htmlspecialchars($message['name']) ?></strong>
                                            (<?= htmlspecialchars($message['email']) ?>)
                                            <span class="ms-3">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                            </span>
                                        </p>
                                        <div class="message-content" id="content-<?= $message['id'] ?>">
                                            <p class="card-text"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                            <?php if (strlen($message['message']) > 200): ?>
                                                <div class="expand-btn">
                                                    <button class="btn btn-sm btn-link"
                                                        onclick="toggleMessage(<?= $message['id'] ?>)">
                                                        <span id="btn-text-<?= $message['id'] ?>">Read more</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($message['admin_reply']): ?>
                                            <div class="mt-3 p-3 bg-success bg-opacity-10 border-start border-success border-3">
                                                <h6 class="text-success mb-2">
                                                    <i class="fas fa-reply me-1"></i>Admin Reply
                                                </h6>
                                                <p class="mb-1"><?= nl2br(htmlspecialchars($message['admin_reply'])) ?></p>
                                                <small class="text-muted">
                                                    Replied by <?= htmlspecialchars($message['replied_by_username']) ?>
                                                    on <?= date('M j, Y g:i A', strtotime($message['replied_at'])) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group-vertical" role="group">
                                            <?php if (!$message['is_read']): ?>
                                                <a href="?mark_read=<?= $message['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>Mark Read
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$message['admin_reply']): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="toggleReplyForm(<?= $message['id'] ?>)">
                                                    <i class="fas fa-reply me-1"></i>Reply
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="toggleReplyForm(<?= $message['id'] ?>)">
                                                    <i class="fas fa-edit me-1"></i>Update Reply
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reply Form -->
                                <div class="reply-form" id="reply-form-<?= $message['id'] ?>">
                                    <form method="POST">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <div class="mb-3">
                                            <label for="admin_reply_<?= $message['id'] ?>" class="form-label">Your Reply</label>
                                            <textarea class="form-control" id="admin_reply_<?= $message['id'] ?>"
                                                name="admin_reply" rows="4"
                                                required><?= $message['admin_reply'] ? htmlspecialchars($message['admin_reply']) : '' ?></textarea>
                                        </div>
                                        <button type="submit" name="reply_message" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Send Reply
                                        </button>
                                        <button type="button" class="btn btn-secondary ms-2"
                                            onclick="toggleReplyForm(<?= $message['id'] ?>)">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleReplyForm(messageId) {
            const form = document.getElementById('reply-form-' + messageId);
            form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
        }

        function toggleMessage(messageId) {
            const content = document.getElementById('content-' + messageId);
            const btnText = document.getElementById('btn-text-' + messageId);

            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                btnText.textContent = 'Read more';
            } else {
                content.classList.add('expanded');
                btnText.textContent = 'Read less';
            }
        }

        // Auto-hide success alerts
        setTimeout(function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>