<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Manage Reminders';
$error = '';
$success = '';

// Handle reminder deletion
if (isset($_GET['delete']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $reminder_id = (int) $_GET['delete'];

    $delete_stmt = $conn->prepare("DELETE FROM reminders WHERE id = ?");
    $delete_stmt->bind_param("i", $reminder_id);

    if ($delete_stmt->execute()) {
        $success = "Reminder deleted successfully.";
    } else {
        $error = "Failed to delete reminder.";
    }
}

// Handle reminder editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reminder'])) {
    $reminder_id = (int) ($_POST['reminder_id'] ?? 0);
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $reminder_type = sanitize($_POST['reminder_type'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $due_date = sanitize($_POST['due_date'] ?? '');
    $due_time = sanitize($_POST['due_time'] ?? null);

    if (!$reminder_id || !$user_id || !$reminder_type || !$title || !$due_date) {
        $error = "All required fields must be filled.";
    } else {
        $query = "UPDATE reminders SET user_id = ?, reminder_type = ?, title = ?, description = ?, due_date = ?, due_time = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssssi", $user_id, $reminder_type, $title, $description, $due_date, $due_time, $reminder_id);

        if ($stmt->execute()) {
            $success = "Reminder updated successfully!";
        } else {
            $error = "Failed to update reminder.";
        }
    }
}

// Handle reminder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $reminder_type = sanitize($_POST['reminder_type'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $due_date = sanitize($_POST['due_date'] ?? '');
    $due_time = sanitize($_POST['due_time'] ?? null);

    if (!$user_id || !$reminder_type || !$title || !$due_date) {
        $error = "All required fields must be filled.";
    } else {
        $query = "INSERT INTO reminders (user_id, reminder_type, title, description, due_date, due_time)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssss", $user_id, $reminder_type, $title, $description, $due_date, $due_time);

        if ($stmt->execute()) {
            $success = "Reminder added successfully!";
        } else {
            $error = "Failed to add reminder.";
        }
    }
}

// Get reminder for editing if edit ID is provided
$edit_reminder = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM reminders WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_reminder = $edit_result->fetch_assoc();
    }
}

// Fetch users and reminders
$users_result = $conn->query("SELECT id, name FROM users ORDER BY name");

$reminders_result = $conn->query("
    SELECT r.*, u.name AS user_name 
    FROM reminders r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.due_date ASC, r.due_time ASC
");

include '../includes/admin_header.php';
?>

<div class="container mt-4">
    <h3 class="mb-4"><i class="fas fa-bell"></i> Manage Reminders</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Add/Edit Reminder Form -->
    <form method="POST" class="card p-4 mb-4">
        <h5><?php echo $edit_reminder ? 'Edit Reminder' : 'Add New Reminder'; ?></h5>

        <?php if ($edit_reminder): ?>
            <input type="hidden" name="reminder_id" value="<?= $edit_reminder['id'] ?>">
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">-- Choose User --</option>
                    <?php
                    // Reset the result pointer for users
                    $users_result = $conn->query("SELECT id, name FROM users ORDER BY name");
                    while ($user = $users_result->fetch_assoc()):
                        ?>
                        <option value="<?= $user['id'] ?>" <?= ($edit_reminder && $edit_reminder['user_id'] == $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Reminder Type</label>
                <select name="reminder_type" class="form-select" required>
                    <option value="appointment" <?= ($edit_reminder && $edit_reminder['reminder_type'] == 'appointment') ? 'selected' : '' ?>>Appointment</option>
                    <option value="medication" <?= ($edit_reminder && $edit_reminder['reminder_type'] == 'medication') ? 'selected' : '' ?>>Medication</option>
                    <option value="milestone" <?= ($edit_reminder && $edit_reminder['reminder_type'] == 'milestone') ? 'selected' : '' ?>>Milestone</option>
                    <option value="custom" <?= ($edit_reminder && $edit_reminder['reminder_type'] == 'custom') ? 'selected' : '' ?>>Custom</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control"
                value="<?= $edit_reminder ? htmlspecialchars($edit_reminder['title']) : '' ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"
                rows="3"><?= $edit_reminder ? htmlspecialchars($edit_reminder['description']) : '' ?></textarea>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control"
                    value="<?= $edit_reminder ? $edit_reminder['due_date'] : '' ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Due Time (optional)</label>
                <input type="time" name="due_time" class="form-control"
                    value="<?= $edit_reminder ? $edit_reminder['due_time'] : '' ?>">
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <?php if ($edit_reminder): ?>
                <a href="manage_reminders.php" class="btn btn-secondary me-md-2">Cancel</a>
                <button type="submit" name="edit_reminder" class="btn btn-success">Update Reminder</button>
            <?php else: ?>
                <button type="submit" name="add_reminder" class="btn btn-primary">Add Reminder</button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Reminders List -->
    <h5 class="mb-3">All Reminders</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Due Date</th>
                    <th>Time</th>
                    <th>Sent?</th>
                    <th>Completed?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reminders_result && $reminders_result->num_rows > 0): ?>
                    <?php while ($reminder = $reminders_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($reminder['user_name']) ?></td>
                            <td><?= ucfirst($reminder['reminder_type']) ?></td>
                            <td><?= htmlspecialchars($reminder['title']) ?></td>
                            <td><?= htmlspecialchars($reminder['description']) ?></td>
                            <td><?= $reminder['due_date'] ?></td>
                            <td><?= $reminder['due_time'] ?? '-' ?></td>
                            <td><?= $reminder['is_sent'] ? '✅' : '❌' ?></td>
                            <td><?= $reminder['is_completed'] ? '✅' : '❌' ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="?edit=<?= $reminder['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="javascript:void(0)"
                                        onclick="confirmDelete(<?= $reminder['id'] ?>, '<?= htmlspecialchars($reminder['title'], ENT_QUOTES) ?>')"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No reminders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function confirmDelete(id, title) {
        if (confirm(`Are you sure you want to delete the reminder "${title}"? This action cannot be undone.`)) {
            window.location.href = `?delete=${id}&confirm=yes`;
        }
    }
</script>

<?php include '../includes/admin_footer.php'; ?>