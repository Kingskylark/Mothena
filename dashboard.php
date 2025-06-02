<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Dashboard';
$current_user = getCurrentUser();

if (!$current_user) {
    redirect('login.php');
}

// Handle admin contact form submission
$contact_message = '';
$contact_error = '';

if ($_POST && isset($_POST['contact_admin'])) {
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
        } else {
            $contact_error = 'Failed to send message. Please try again.';
        }
        $stmt->close();
    }
}

// Get user's recent messages and replies (last 5)
$user_messages = [];
$stmt = $conn->prepare("
    SELECT id, subject, message, admin_reply, is_read, created_at, replied_at 
    FROM user_messages 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $current_user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_messages[] = $row;
}
$stmt->close();

// Count unread replies
$unread_replies = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM user_messages 
    WHERE user_id = ? AND admin_reply IS NOT NULL AND admin_reply != '' AND is_read = 0
");
$stmt->bind_param("i", $current_user['id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_replies = $row['count'];
$stmt->close();

// Get personalized content for the user
$personalized_content = getPersonalizedContent($current_user['id'], 6);
$personalized_tips = getPersonalizedTips($current_user['id'], 3);
$upcoming_reminders = getPersonalizedReminders($current_user['id'], 5);

// Calculate pregnancy week if due date is set
$pregnancy_week = 0;
$days_remaining = 0;
if (!empty($current_user['due_date'])) {
    $pregnancy_week = getPregnancyWeek($current_user['due_date']);
    $due_date = new DateTime($current_user['due_date']);
    $now = new DateTime();
    $interval = $now->diff($due_date);
    $days_remaining = $interval->days;
    if ($now > $due_date) {
        $days_remaining = -$days_remaining; // Past due
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?>!</h1>
            <p class="text-muted">Here's your personalized pregnancy dashboard</p>
        </div>
    </div>

    <!-- Pregnancy Progress Section -->
    <?php if (!empty($current_user['due_date'])): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="pregnancy-week">
                    <h3><i class="fas fa-baby"></i> Week <?php echo $pregnancy_week; ?></h3>
                    <p class="mb-1">Trimester <?php echo $current_user['trimester']; ?></p>
                    <p class="mb-0">
                        <?php if ($days_remaining > 0): ?>
                            <?php echo $days_remaining; ?> days until due date
                        <?php elseif ($days_remaining < 0): ?>
                            <?php echo abs($days_remaining); ?> days past due date
                        <?php else: ?>
                            Due date is today!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-alt"></i> Due Date</h5>
                        <h4 class="text-primary"><?php echo date('F j, Y', strtotime($current_user['due_date'])); ?></h4>
                        <div class="progress mt-3">
                            <div class="progress-bar" role="progressbar"
                                style="width: <?php echo min(100, ($pregnancy_week / 40) * 100); ?>%">
                                <?php echo round(($pregnancy_week / 40) * 100, 1); ?>% Complete
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user fa-2x text-primary mb-2"></i>
                    <h6>Age</h6>
                    <h4><?php echo $current_user['age']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-success mb-2"></i>
                    <h6>Trimester</h6>
                    <h4><?php echo $current_user['trimester']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                    <h6>Member Since</h6>
                    <h6><?php echo date('M Y', strtotime($current_user['created_at'])); ?></h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-star fa-2x text-warning mb-2"></i>
                    <h6>Interests</h6>
                    <h6><?php echo !empty($current_user['interests']) ? count(explode(',', $current_user['interests'])) : 0; ?>
                    </h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column - Content & Tips -->
        <div class="col-lg-8">
            <!-- Personalized Tips -->
            <?php if (!empty($personalized_tips)): ?>
                <div class="tips-section mb-4">
                    <h5><i class="fas fa-lightbulb"></i> Tips for You</h5>
                    <?php foreach ($personalized_tips as $tip): ?>
                        <div class="mb-2">
                            <strong><?php echo htmlspecialchars($tip['title']); ?></strong>
                            <p class="mb-1 small"><?php echo htmlspecialchars(substr($tip['content_text'], 0, 100)); ?>...</p>
                        </div>
                    <?php endforeach; ?>
                    <a href="content.php?type=tip" class="btn btn-sm btn-outline-warning">View All Tips</a>
                </div>
            <?php endif; ?>

            <!-- Personalized Content -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-newspaper"></i> Recommended for You</h5>
                    <a href="content.php" class="btn btn-sm btn-outline-primary">View All Content</a>
                </div>

                <?php if (!empty($personalized_content)): ?>
                    <div class="row">
                        <?php foreach ($personalized_content as $content): ?>
                            <div class="col-md-6 mb-3">
                                <?php
                                // Get user interactions for this content
                                $user_interactions = getUserInteractions($current_user['id'], $content['id']);
                                $interaction_counts = getInteractionCounts($content['id']);
                                $is_liked = in_array('like', $user_interactions);
                                $is_bookmarked = in_array('bookmark', $user_interactions);
                                ?>
                                <div class="card content-card h-100" data-content-id="<?php echo $content['id']; ?>">
                                    <div class="card-header">
                                        <span
                                            class="badge bg-primary"><?php echo ucwords(str_replace('_', ' ', $content['category'])); ?></span>
                                        <?php if ($content['is_featured']): ?>
                                            <span class="badge bg-warning">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($content['title']); ?></h6>
                                        <p class="card-text small">
                                            <?php echo htmlspecialchars(substr($content['content_text'], 0, 120)); ?>...</p>

                                        <!-- Interaction buttons -->
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button"
                                                    class="btn btn-outline-danger btn-sm like-btn <?php echo $is_liked ? 'active' : ''; ?>"
                                                    data-content-id="<?php echo $content['id']; ?>">
                                                    <i class="fas fa-heart"></i> <span
                                                        class="like-count"><?php echo $interaction_counts['like']; ?></span>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-outline-warning btn-sm bookmark-btn <?php echo $is_bookmarked ? 'active' : ''; ?>"
                                                    data-content-id="<?php echo $content['id']; ?>">
                                                    <i class="fas fa-bookmark"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm share-btn"
                                                    data-content-id="<?php echo $content['id']; ?>">
                                                    <i class="fas fa-share"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <span
                                                    class="view-count"><?php echo $content['view_count']; ?></span> views
                                            </small>
                                            <small class="text-muted">
                                                <?php echo date('M j', strtotime($content['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No personalized content available yet.
                        <a href="profile.php">Update your profile</a> to get better recommendations.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column - Reminders & Quick Actions -->
        <div class="col-lg-4">
            <!-- My Messages Section - NEW -->
            <?php if (!empty($user_messages)): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-comments"></i> My Messages
                            <?php if ($unread_replies > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $unread_replies; ?> new</span>
                            <?php endif; ?>
                        </h5>
                        <a href="messages.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($user_messages as $msg): ?>
                            <div
                                class="message-item mb-3 p-2 border rounded <?php echo (!empty($msg['is_read']) && $msg['is_read'] == 0) ? 'border-primary bg-light' : ''; ?>">
                                <!-- User's Message -->
                                <div class="user-message mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong class="text-primary"><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                        <span
                                            class="badge bg-<?php echo $msg['status'] == 'replied' ? 'success' : ($msg['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                    </div>
                                    <p class="small mb-1"><?php echo htmlspecialchars(substr($msg['message'], 0, 100)); ?>...
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                    </small>
                                </div>

                                <!-- Admin's Reply -->
                                <?php if (!empty($msg['admin_reply'])): ?>
                                    <div class="admin-reply mt-2 p-2 bg-success bg-opacity-10 border-start border-success border-3">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-user-shield text-success me-1"></i>
                                            <strong class="text-success small">Admin Reply:</strong>
                                            <?php if ($msg['is_read'] == 0): ?>
                                                <span class="badge bg-danger ms-2 small">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="small mb-1"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-reply"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($msg['replied_at'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- My Bookmarks Section -->
            <?php
            $user_bookmarks = getUserBookmarks($current_user['id'], 3);
            if (!empty($user_bookmarks)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bookmark"></i> My Bookmarks</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($user_bookmarks as $bookmark): ?>
                            <div class="mb-2 p-2 border rounded">
                                <strong class="small"><?php echo htmlspecialchars($bookmark['title']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-tag"></i>
                                    <?php echo ucwords(str_replace('_', ' ', $bookmark['category'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <a href="bookmarks.php" class="btn btn-sm btn-outline-warning mt-2">View All Bookmarks</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Contact Admin Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-envelope"></i> Contact Admin</h5>
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
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select form-select-sm" name="subject" required>
                                <option value="">Select a topic...</option>
                                <option value="General Question">General Question</option>
                                <option value="Technical Issue">Technical Issue</option>
                                <option value="Content Request">Content Request</option>
                                <option value="Feedback">Feedback</option>
                                <option value="Account Issue">Account Issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control form-control-sm" name="message" rows="4"
                                placeholder="Type your message here..." required></textarea>
                        </div>
                        <button type="submit" name="contact_admin" class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Upcoming Reminders -->
            <?php if (!empty($upcoming_reminders)): ?>
                <div class="reminders-section mb-4">
                    <h5><i class="fas fa-bell"></i> Upcoming Reminders</h5>
                    <?php foreach ($upcoming_reminders as $reminder): ?>
                        <div class="mb-2 p-2 border rounded">
                            <strong><?php echo htmlspecialchars($reminder['title']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($reminder['due_date'])); ?>
                            </small>
                            <?php if (!empty($reminder['description'])): ?>
                                <br>
                                <small><?php echo htmlspecialchars($reminder['description']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="manage_reminders.php" class="btn btn-sm btn-outline-purple mt-2">Manage Reminders</a>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-rocket"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                        <a href="content.php?category=nutrition" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-apple-alt"></i> Nutrition Guide
                        </a>
                        <a href="content.php?category=exercise" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-dumbbell"></i> Exercise Tips
                        </a>
                        <a href="content.php?trimester=<?php echo $current_user['trimester']; ?>"
                            class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-calendar-week"></i> Trimester <?php echo $current_user['trimester']; ?>
                            Info
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile Completion -->
            <?php
            $profile_completion = 0;
            $total_fields = 6;
            $completed_fields = 0;

            if (!empty($current_user['name']))
                $completed_fields++;
            if (!empty($current_user['email']))
                $completed_fields++;
            if (!empty($current_user['age']))
                $completed_fields++;
            if (!empty($current_user['due_date']))
                $completed_fields++;
            if (!empty($current_user['trimester']))
                $completed_fields++;
            if (!empty($current_user['interests']))
                $completed_fields++;

            $profile_completion = ($completed_fields / $total_fields) * 100;
            ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-check"></i> Profile Completion</h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-2">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $profile_completion; ?>%">
                            <?php echo round($profile_completion); ?>%
                        </div>
                    </div>
                    <p class="small mb-2"><?php echo $completed_fields; ?> of <?php echo $total_fields; ?> fields
                        completed</p>
                    <?php if ($profile_completion < 100): ?>
                        <a href="profile.php" class="btn btn-sm btn-primary">Complete Profile</a>
                    <?php else: ?>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Profile Complete!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add some custom styles for purple color since Bootstrap doesn't have it by default -->
<style>
    .btn-outline-purple {
        color: #9c27b0;
        border-color: #9c27b0;
    }

    .btn-outline-purple:hover {
        color: white;
        background-color: #9c27b0;
        border-color: #9c27b0;
    }

    .content-card {
        transition: transform 0.2s;
    }

    .content-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .like-btn.active {
        background-color: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    .bookmark-btn.active {
        background-color: #ffc107;
        color: #212529;
        border-color: #ffc107;
    }

    .message-item {
        transition: all 0.3s ease;
    }

    .message-item:hover {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .admin-reply {
        position: relative;
    }

    .admin-reply::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 0;
        bottom: 0;
        width: 4px;
        background-color: #198754;
        border-radius: 2px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Mark messages as read when clicked
        document.querySelectorAll('.message-item').forEach(item => {
            item.addEventListener('click', function () {
                const messageId = this.dataset.messageId;
                if (messageId) {
                    // Mark as read via AJAX
                    fetch('ajax/mark_message_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `message_id=${messageId}`
                    });
                }
            });
        });

        // Handle like button clicks
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function () {
                const contentId = this.dataset.contentId;
                handleInteraction(contentId, 'like', this);
            });
        });

        // Handle bookmark button clicks
        document.querySelectorAll('.bookmark-btn').forEach(button => {
            button.addEventListener('click', function () {
                const contentId = this.dataset.contentId;
                handleInteraction(contentId, 'bookmark', this);
            });
        });

        // Handle share button clicks
        document.querySelectorAll('.share-btn').forEach(button => {
            button.addEventListener('click', function () {
                const contentId = this.dataset.contentId;
                handleInteraction(contentId, 'share', this);

                // Simple share functionality - copy URL to clipboard
                const url = window.location.origin + '/content.php?id=' + contentId;
                navigator.clipboard.writeText(url).then(() => {
                    // Show temporary feedback
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            });
        });

        // Handle content card clicks to track views
        document.querySelectorAll('.content-card').forEach(card => {
            card.addEventListener('click', function (e) {
                // Don't track view if clicking on interaction buttons
                if (e.target.closest('.btn-group')) return;

                const contentId = this.dataset.contentId;
                handleInteraction(contentId, 'view', null);

                // Redirect to content page
                window.location.href = 'content.php?id=' + contentId;
            });
        });

        function handleInteraction(contentId, interactionType, button) {
            fetch('ajax/interaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=interact&content_id=${contentId}&interaction_type=${interactionType}`
            })
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Get the text first to see what we're actually receiving
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug log

                    // Check if response is empty
                    if (!text.trim()) {
                        throw new Error('Empty response from server');
                    }

                    // Try to parse JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                })
                .then(data => {
                    console.log('Parsed data:', data); // Debug log

                    if (data.success) {
                        // Update UI based on interaction type
                        if (interactionType === 'like' && button) {
                            const likeCount = button.querySelector('.like-count');
                            if (likeCount) {
                                likeCount.textContent = data.counts.like;
                            }

                            if (data.action === 'added') {
                                button.classList.add('active');
                            } else {
                                button.classList.remove('active');
                            }
                        }

                        if (interactionType === 'bookmark' && button) {
                            if (data.action === 'added') {
                                button.classList.add('active');
                            } else {
                                button.classList.remove('active');
                            }
                        }

                        if (interactionType === 'view') {
                            // Update view count in the card
                            const card = document.querySelector(`[data-content-id="${contentId}"]`);
                            const viewCount = card ? card.querySelector('.view-count') : null;
                            if (viewCount) {
                                viewCount.textContent = data.counts.view;
                            }
                        }
                    } else {
                        console.error('Server error:', data.error);
                        // Optional: Show user-friendly error message
                        // alert('Something went wrong. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Optional: Show user-friendly error message
                    // alert('Something went wrong. Please try again.');
                });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>