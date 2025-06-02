<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';


if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Manage Users';
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Age', 'Trimester', 'Interests', 'Created At']);
    $all_users = getAllUsers(1, 1000, $search); // Limit to 1000 for export
    foreach ($all_users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['age'],
            $user['trimester'],
            $user['interests'],
            $user['created_at']
        ]);
    }
    fclose($output);
    exit();
}

// Handle toggle activation
if (isset($_GET['toggle_id'])) {
    $user_id = (int) $_GET['toggle_id'];
    toggleUserStatus($user_id);
    redirect('users.php?page=' . $page . '&search=' . urlencode($search));
}

// Handle update (from modal form)
if ($_POST && isset($_POST['update_user'])) {
    $user_id = (int) $_POST['user_id'];
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $age = (int) $_POST['age'];
    $due_date = sanitize($_POST['due_date'] ?? '');
    $trimester = (int) $_POST['trimester'];
    $location = sanitize($_POST['location'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $language_preference = sanitize($_POST['language_preference'] ?? 'english');
    $medical_history = sanitize($_POST['medical_history'] ?? '');
    $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    updateUser($user_id, [
        'name' => $name,
        'email' => $email,
        'age' => $age,
        'due_date' => $due_date,
        'trimester' => $trimester,
        'location' => $location,
        'phone' => $phone,
        'language_preference' => $language_preference,
        'medical_history' => $medical_history,
        'interests' => $interests,
        'is_active' => $is_active
    ]);

    redirect('users.php?page=' . $page . '&search=' . urlencode($search));
}
//Handle new users
if ($_POST && isset($_POST['add_user'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $age = (int) ($_POST['age'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? '');
    $trimester = (int) ($_POST['trimester'] ?? 1);
    $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
    $location = sanitize($_POST['location'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    // Process medical history
    $personal_medical = isset($_POST['personal_medical']) ? implode(',', $_POST['personal_medical']) : '';
    $family_medical = isset($_POST['family_medical']) ? implode(',', $_POST['family_medical']) : '';
    $allergies = isset($_POST['allergies']) ? implode(',', $_POST['allergies']) : '';
    $medical_history_details = sanitize($_POST['medical_history_details'] ?? '');

    // Combine all medical information
    $medical_history_parts = array_filter([
        $personal_medical ? "Personal: $personal_medical" : '',
        $family_medical ? "Family: $family_medical" : '',
        $allergies ? "Allergies: $allergies" : '',
        $medical_history_details ? "Details: $medical_history_details" : ''
    ]);
    $medical_history = implode(' | ', $medical_history_parts);

    $language_preference = sanitize($_POST['language_preference'] ?? 'english');

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($age < 13 || $age > 60) {
        $error = 'Please enter a valid age between 13 and 60.';
    } elseif (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif (empty($trimester) || !in_array($trimester, [1, 2, 3])) {
        $error = 'Please select a valid trimester.';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check_result = $stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error = 'Email address is already registered.';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (name, email, password, age, due_date, trimester, interests, location, phone, medical_history, language_preference, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssisssssss", $name, $email, $hashed_password, $age, $due_date, $trimester, $interests, $location, $phone, $medical_history, $language_preference);

            if ($stmt->execute()) {
                $success = 'User account created successfully!';
                // Clear form data after successful submission
                unset($_POST);
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$users = getAllUsers($page, $per_page, $search);
$total_users = getTotalUsers($search);
$total_pages = ceil($total_users / $per_page);

include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>User Management</h2>
        <a href="users.php?export=csv" class="btn btn-success btn-sm">Export CSV</a>
        <a href="#" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">Add New
            User</a>
    </div>

    <form method="GET" class="d-flex mb-3">
        <input type="text" name="search" class="form-control me-2" placeholder="Search users..."
            value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-primary">Search</button>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Age</th>
                    <th>Due Date</th>
                    <th>Trimester</th>
                    <th>Location</th>
                    <th>Phone</th>
                    <th>Language</th>
                    <th>Interests</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $user['age']; ?> yrs</span>
                        </td>
                        <td>
                            <?php if (!empty($user['due_date'])): ?>
                                <small><?php echo date('M j, Y', strtotime($user['due_date'])); ?></small>
                            <?php else: ?>
                                <small class="text-muted">Not set</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $trimester_colors = [1 => 'success', 2 => 'warning', 3 => 'danger'];
                            $trimester_names = [1 => '1st', 2 => '2nd', 3 => '3rd'];
                            ?>
                            <span class="badge bg-<?php echo $trimester_colors[$user['trimester']]; ?>">
                                <?php echo $trimester_names[$user['trimester']]; ?> Trimester
                            </span>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($user['location'] ?? 'Not specified'); ?></small>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></small>
                        </td>
                        <td>
                            <small><?php echo ucfirst($user['language_preference'] ?? 'English'); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($user['interests'])): ?>
                                <?php
                                $interests = explode(',', $user['interests']);
                                $displayInterests = array_slice($interests, 0, 2); // Show first 2
                                ?>
                                <small>
                                    <?php foreach ($displayInterests as $interest): ?>
                                        <span
                                            class="badge bg-secondary me-1"><?php echo ucwords(str_replace('_', ' ', trim($interest))); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($interests) > 2): ?>
                                        <span class="text-muted">+<?php echo count($interests) - 2; ?> more</span>
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">None selected</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($user['is_active'] ?? 1)): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#editUserModal<?php echo $user['id']; ?>" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                    data-bs-target="#viewUserModal<?php echo $user['id']; ?>" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="?toggle_id=<?php echo $user['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>"
                                    class="btn btn-sm <?php echo ($user['is_active'] ?? 1) ? 'btn-warning' : 'btn-success'; ?>"
                                    title="<?php echo ($user['is_active'] ?? 1) ? 'Deactivate' : 'Activate'; ?> User"
                                    onclick="return confirm('Are you sure you want to <?php echo ($user['is_active'] ?? 1) ? 'deactivate' : 'activate'; ?> this user?')">
                                    <i
                                        class="fas fa-<?php echo ($user['is_active'] ?? 1) ? 'user-slash' : 'user-check'; ?>"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <!-- View User Details Modals -->
    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">User Details: <?php echo htmlspecialchars($user['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-user"></i> Basic Information</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Full Name:</strong></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Age:</strong></td>
                                        <td><?php echo $user['age']; ?> years old</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Due Date:</strong></td>
                                        <td>
                                            <?php if (!empty($user['due_date'])): ?>
                                                <?php echo date('F j, Y', strtotime($user['due_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Trimester:</strong></td>
                                        <td>
                                            <?php
                                            $trimester_names = [1 => 'First Trimester', 2 => 'Second Trimester', 3 => 'Third Trimester'];
                                            echo $trimester_names[$user['trimester']];
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Account Status:</strong></td>
                                        <td>
                                            <?php if (($user['is_active'] ?? 1)): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Contact Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-address-book"></i> Contact Information</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Location:</strong></td>
                                        <td><?php echo htmlspecialchars($user['location'] ?? 'Not specified'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Language:</strong></td>
                                        <td><?php echo ucfirst($user['language_preference'] ?? 'English'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Registered:</strong></td>
                                        <td><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Interests -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3"><i class="fas fa-heart"></i> Interests & Preferences</h6>
                                <?php if (!empty($user['interests'])): ?>
                                    <?php
                                    $interests = explode(',', $user['interests']);
                                    ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($interests as $interest): ?>
                                            <span
                                                class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', trim($interest))); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No interests selected</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Medical History -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3"><i class="fas fa-heartbeat"></i> Medical History</h6>
                                <?php if (!empty($user['medical_history'])): ?>
                                    <div class="border rounded p-3 bg-light">
                                        <pre class="mb-0"
                                            style="white-space: pre-wrap; font-family: inherit;"><?php echo htmlspecialchars($user['medical_history']); ?></pre>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No medical history recorded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#editUserModal<?php echo $user['id']; ?>" data-bs-dismiss="modal">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3"><i class="fas fa-user"></i> Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control"
                                        value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Age <span class="text-danger">*</span></label>
                                    <input type="number" name="age" class="form-control" value="<?php echo $user['age']; ?>"
                                        min="13" max="60" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" name="due_date" class="form-control"
                                        value="<?php echo $user['due_date']; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Trimester <span class="text-danger">*</span></label>
                                    <select name="trimester" class="form-control" required>
                                        <option value="1" <?php if ($user['trimester'] == 1)
                                            echo 'selected'; ?>>First
                                            Trimester</option>
                                        <option value="2" <?php if ($user['trimester'] == 2)
                                            echo 'selected'; ?>>Second
                                            Trimester</option>
                                        <option value="3" <?php if ($user['trimester'] == 3)
                                            echo 'selected'; ?>>Third
                                            Trimester</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-address-book"></i> Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control"
                                        value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Language Preference</label>
                            <select name="language_preference" class="form-control">
                                <option value="english" <?php if (($user['language_preference'] ?? 'english') == 'english')
                                    echo 'selected'; ?>>English</option>
                                <option value="french" <?php if (($user['language_preference'] ?? '') == 'french')
                                    echo 'selected'; ?>>French</option>
                                <option value="hausa" <?php if (($user['language_preference'] ?? '') == 'hausa')
                                    echo 'selected'; ?>>Hausa</option>
                                <option value="yoruba" <?php if (($user['language_preference'] ?? '') == 'yoruba')
                                    echo 'selected'; ?>>Yoruba</option>
                                <option value="igbo" <?php if (($user['language_preference'] ?? '') == 'igbo')
                                    echo 'selected'; ?>>Igbo</option>
                            </select>
                        </div>

                        <!-- Preferences -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-heart"></i> Interests & Preferences</h6>
                        <div class="mb-3">
                            <label class="form-label">Interests</label>
                            <?php
                            $allInterests = ['nutrition', 'exercise', 'mental_health', 'baby_development', 'labor_preparation', 'breastfeeding'];
                            $userInterests = explode(',', $user['interests']);
                            ?>
                            <div class="row">
                                <?php foreach ($allInterests as $interest): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" name="interests[]" value="<?php echo $interest; ?>"
                                                class="form-check-input" id="edit_<?php echo $interest . $user['id']; ?>" <?php echo in_array($interest, $userInterests) ? 'checked' : ''; ?> <label
                                                class="form-check-label" for="edit_<?php echo $interest . $user['id']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $interest)); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Medical History -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-heartbeat"></i> Medical History</h6>
                        <div class="mb-3">
                            <label class="form-label">Medical History & Health Information</label>
                            <textarea name="medical_history" class="form-control" rows="4"
                                placeholder="Enter medical history, conditions, allergies, family history, medications, etc..."><?php echo htmlspecialchars($user['medical_history'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Include any relevant medical conditions, allergies,
                                medications, or family history that may affect pregnancy.</small>
                        </div>

                        <!-- Account Status -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-cog"></i> Account Status</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                    id="active_<?php echo $user['id']; ?>" <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active_<?php echo $user['id']; ?>">
                                    Account Active
                                </label>
                            </div>
                            <small class="form-text text-muted">Uncheck to deactivate the user account.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>


    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Age <span class="text-danger">*</span></label>
                        <input type="number" name="age" class="form-control" min="13" max="60" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trimester <span class="text-danger">*</span></label>
                        <select name="trimester" class="form-control" required>
                            <option value="">Select Trimester</option>
                            <option value="1">First Trimester</option>
                            <option value="2">Second Trimester</option>
                            <option value="3">Third Trimester</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g., +234XXXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Language Preference</label>
                        <select name="language_preference" class="form-control">
                            <option value="english" selected>English</option>
                            <option value="french">French</option>
                            <option value="hausa">Hausa</option>
                            <option value="yoruba">Yoruba</option>
                            <option value="igbo">Igbo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interests</label>
                        <div class="row">
                            <?php
                            $allInterests = ['nutrition', 'exercise', 'mental_health', 'baby_development', 'labor_preparation', 'breastfeeding'];
                            foreach ($allInterests as $interest):
                                ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="interests[]"
                                            value="<?php echo $interest; ?>" id="add_<?php echo $interest; ?>">
                                        <label class="form-check-label" for="add_<?php echo $interest; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $interest)); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Medical History -->
                    <h5 class="mb-3 text-primary"><i class="fas fa-heartbeat"></i> Medical History & Family Background
                    </h5>

                    <!-- Personal Medical History -->
                    <div class="mb-3">
                        <h6 class="text-secondary mb-2">Personal Medical History</h6>
                        <div class="row">
                            <?php
                            $personal_conditions = [
                                'hypertension' => 'High Blood Pressure/Hypertension',
                                'diabetes' => 'Diabetes',
                                'gestational_diabetes' => 'Previous Gestational Diabetes',
                                'asthma' => 'Asthma',
                                'heart_disease' => 'Heart Disease',
                                'kidney_disease' => 'Kidney Disease',
                                'thyroid_disorder' => 'Thyroid Disorder',
                                'epilepsy' => 'Epilepsy/Seizure Disorder',
                                'depression_anxiety' => 'Depression/Anxiety',
                                'blood_clotting' => 'Blood Clotting Disorders',
                                'anemia' => 'Anemia',
                                'previous_miscarriage' => 'Previous Miscarriage',
                                'previous_preterm' => 'Previous Preterm Birth',
                                'previous_cesarean' => 'Previous C-Section'
                            ];
                            foreach ($personal_conditions as $key => $label):
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="personal_medical[]"
                                            value="<?php echo $key; ?>" id="personal_<?php echo $key; ?>">
                                        <label class="form-check-label small" for="personal_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Family History -->
                    <div class="mb-3">
                        <h6 class="text-secondary mb-2">Family History</h6>
                        <div class="row">
                            <?php
                            $family_conditions = [
                                'family_hypertension' => 'High Blood Pressure',
                                'family_diabetes' => 'Diabetes',
                                'family_heart_disease' => 'Heart Disease',
                                'family_stroke' => 'Stroke',
                                'family_cancer' => 'Cancer',
                                'family_mental_health' => 'Mental Health Disorders',
                                'family_birth_defects' => 'Birth Defects',
                                'family_genetic_disorders' => 'Genetic Disorders',
                                'family_twins' => 'History of Twins/Multiple Births',
                                'family_pregnancy_complications' => 'Pregnancy Complications'
                            ];
                            foreach ($family_conditions as $key => $label):
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="family_medical[]"
                                            value="<?php echo $key; ?>" id="family_<?php echo $key; ?>">
                                        <label class="form-check-label small" for="family_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Allergies -->
                    <div class="mb-3">
                        <h6 class="text-secondary mb-2">Known Allergies</h6>
                        <div class="row">
                            <?php
                            $allergies = [
                                'drug_allergies' => 'Drug/Medication Allergies',
                                'food_allergies' => 'Food Allergies',
                                'environmental_allergies' => 'Environmental Allergies',
                                'latex_allergy' => 'Latex Allergy'
                            ];
                            foreach ($allergies as $key => $label):
                                ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allergies[]"
                                            value="<?php echo $key; ?>" id="allergy_<?php echo $key; ?>">
                                        <label class="form-check-label small" for="allergy_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Additional Medical Details -->
                    <div class="mb-4">
                        <label for="medical_history_details" class="form-label">Additional Medical Information</label>
                        <textarea class="form-control" name="medical_history_details" rows="3"
                            placeholder="Please provide any additional details about the conditions selected above, current medications, or other relevant health information..."></textarea>
                        <small class="form-text text-muted">Include specific medications, severity of conditions, or any
                            other important details.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_user" class="btn btn-success">Add User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if ($i == $page)
                    echo 'active'; ?>">
                    <a class="page-link"
                        href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<?php include '../includes/admin_footer.php'; ?>