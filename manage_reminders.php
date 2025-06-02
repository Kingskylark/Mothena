<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$current_user = getCurrentUser();
$upcoming_reminders = getPersonalizedReminders($current_user['id'], 5);

$message = '';

// Handle mark as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $reminder_id = (int) $_POST['reminder_id'];
    $stmt = $conn->prepare("UPDATE reminders SET is_completed = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reminder_id, $current_user['id']);
    $stmt->execute();
    $message = 'Reminder marked as completed.';

    // Refresh the reminders list after update
    $upcoming_reminders = getPersonalizedReminders($current_user['id'], 5);
}

$page_title = 'Manage My Reminders';
include './includes/header.php'; // Or your user layout
?>

<div class="container mt-4">
    <h4><i class="fas fa-tasks"></i> Manage My Reminders</h4>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($upcoming_reminders)): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Due Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming_reminders as $reminder): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reminder['title']); ?></td>
                        <td><?php echo htmlspecialchars($reminder['due_date']); ?></td>
                        <td><?php echo $reminder['due_time'] ?? '--'; ?></td>
                        <td>
                            <?php echo $reminder['is_completed'] ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning text-dark">Pending</span>'; ?>
                        </td>
                        <td>
                            <?php if (!$reminder['is_completed']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="reminder_id" value="<?= $reminder['id']; ?>">
                                    <button type="submit" name="mark_completed" class="btn btn-sm btn-success">Mark as Done</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">You don't have any reminders yet.</p>
    <?php endif; ?>
</div>

<?php include './includes/footer.php'; ?>