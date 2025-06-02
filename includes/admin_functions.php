<?php
require_once 'config.php';

// ---------------------- USERS ----------------------

// Get all users with pagination and search
function getAllUsers($page = 1, $per_page = 20, $search = '')
{
    global $conn;
    $offset = ($page - 1) * $per_page;
    $search_clause = '';

    if (!empty($search)) {
        $search = sanitize($search);
        $search_clause = "WHERE name LIKE '%$search%' OR email LIKE '%$search%'";
    }

    $query = "SELECT * FROM users $search_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Total user count for pagination
function getTotalUsers($search = '')
{
    global $conn;
    $search_clause = '';
    if (!empty($search)) {
        $search = sanitize($search);
        $search_clause = "WHERE name LIKE '%$search%' OR email LIKE '%$search%'";
    }
    $result = $conn->query("SELECT COUNT(*) as total FROM users $search_clause");
    return $result ? $result->fetch_assoc()['total'] : 0;
}

// Get single user
function getUserById($user_id)
{
    global $conn;
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc() : null;
}

// Update user
function updateUser($user_id, $data)
{
    global $conn;
    $updates = [];
    foreach ($data as $key => $value) {
        $value = sanitize($value);
        $updates[] = "$key = '$value'";
    }
    $update_clause = implode(', ', $updates);
    $query = "UPDATE users SET $update_clause WHERE id = $user_id";
    return $conn->query($query);
}

// Activate/deactivate user
function toggleUserStatus($user_id)
{
    global $conn;
    $user = getUserById($user_id);
    if ($user) {
        $new_status = $user['is_active'] ? 0 : 1;
        return $conn->query("UPDATE users SET is_active = $new_status WHERE id = $user_id");
    }
    return false;
}

// Delete user
function deleteUser($user_id)
{
    global $conn;
    return $conn->query("DELETE FROM users WHERE id = $user_id");
}

// ---------------------- CONTENT ----------------------

// Get all content with pagination and optional search
function getAllContent($page = 1, $per_page = 20, $search = '')
{
    global $conn;
    $offset = ($page - 1) * $per_page;
    $search_clause = '';

    if (!empty($search)) {
        $search = sanitize($search);
        $search_clause = "WHERE title LIKE '%$search%' OR category LIKE '%$search%'";
    }

    $query = "SELECT * FROM content $search_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get total content count
function getTotalContent($search = '')
{
    global $conn;
    $search_clause = '';
    if (!empty($search)) {
        $search = sanitize($search);
        $search_clause = "WHERE title LIKE '%$search%' OR category LIKE '%$search%'";
    }

    $query = "SELECT COUNT(*) as total FROM content $search_clause";
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc()['total'] : 0;
}

// Get single content item
function getContentById($content_id)
{
    global $conn;
    $query = "SELECT * FROM content WHERE id = $content_id";
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc() : null;
}

// Create new content
function createContent($data)
{
    global $conn;
    $fields = [];
    $values = [];

    foreach ($data as $key => $value) {
        $fields[] = $key;
        $values[] = "'" . $conn->real_escape_string($value) . "'";
    }

    $fields_clause = implode(', ', $fields);
    $values_clause = implode(', ', $values);

    $query = "INSERT INTO content ($fields_clause) VALUES ($values_clause)";
    return $conn->query($query);
}

// Update content
function updateContent($content_id, $data)
{
    global $conn;
    $updates = [];
    foreach ($data as $key => $value) {
        $value = $conn->real_escape_string($value);
        $updates[] = "$key = '$value'";
    }

    $update_clause = implode(', ', $updates);
    $query = "UPDATE content SET $update_clause WHERE id = $content_id";
    return $conn->query($query);
}

// Delete content
function deleteContent($content_id)
{
    global $conn;
    return $conn->query("DELETE FROM content WHERE id = $content_id");
}

// Log admin activity
function logAdminActivity($admin_id, $action, $target_type, $target_id = null, $description = null, $ip_address = null, $user_agent = null)
{
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log 
        (admin_id, action, target_type, target_id, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("ississs", $admin_id, $action, $target_type, $target_id, $description, $ip_address, $user_agent);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Failed to execute admin activity log: " . $stmt->error);
            $stmt->close();
            return false;
        }
    } else {
        error_log("Failed to prepare admin activity log statement: " . $conn->error);
        return false;
    }
}
// Admin login verification
function verifyAdminLogin($username, $password)
{
    global $conn;

    $username = sanitize($username);
    $query = "SELECT * FROM admin_users WHERE username = '$username' AND is_active = TRUE";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        // Check lock
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account is temporarily locked'];
        }

        // Check password (MD5 used for demo purposes)
        if (md5($password) === $admin['password']) {
            $conn->query("UPDATE admin_users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = " . $admin['id']);
            return ['success' => true, 'admin' => $admin];
        } else {
            $attempts = $admin['login_attempts'] + 1;
            $lock_time = ($attempts >= 5) ? ", locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)" : '';
            $conn->query("UPDATE admin_users SET login_attempts = $attempts $lock_time WHERE id = " . $admin['id']);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
    }

    return ['success' => false, 'message' => 'Invalid credentials'];
}



// Enhanced dashboard stats
function getDashboardStats()
{
    global $conn;

    $stats = [];


    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Count new users for the current month
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stats['new_users_month'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Total content
    $result = $conn->query("SELECT COUNT(*) as count FROM content");
    $stats['total_content'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Total views
    $result = $conn->query("SELECT SUM(view_count) as total FROM content");
    $stats['total_views'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

    // Users by trimester
    $result = $conn->query("
        SELECT 
            trimester,
            COUNT(*) as count 
        FROM users 
        WHERE trimester IS NOT NULL 
        GROUP BY trimester
    ");

    $stats['trimester_1'] = 0;
    $stats['trimester_2'] = 0;
    $stats['trimester_3'] = 0;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['trimester_' . $row['trimester']] = $row['count'];
        }
    }

    return $stats;
}

// Get content analytics
function getContentAnalytics()
{
    global $conn;

    $analytics = [];

    // Overall content stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_content,
            SUM(view_count) as total_views,
            AVG(view_count) as avg_views,
            MAX(view_count) as max_views,
            COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_content
        FROM content
    ");

    if ($result) {
        $analytics = $result->fetch_assoc();
    }

    return $analytics;
}

// Get trending content
function getTrendingContent($limit = 10)
{
    global $conn;

    $limit = intval($limit);

    $query = "
        SELECT 
            id,
            title,
            category,
            trimester,
            content_type,
            is_featured,
            view_count as actual_view_count,
            created_at,
            updated_at
        FROM content 
        ORDER BY view_count DESC, created_at DESC
        LIMIT $limit
    ";

    $result = $conn->query($query);

    if ($result) {
        $content = [];
        while ($row = $result->fetch_assoc()) {
            $content[] = $row;
        }
        return $content;
    }

    return [];
}

// Get category statistics
function getCategoryStats()
{
    global $conn;

    $query = "
        SELECT 
            category,
            COUNT(*) as content_count,
            SUM(view_count) as total_views,
            AVG(view_count) as avg_views
        FROM content 
        GROUP BY category 
        ORDER BY total_views DESC
    ";

    $result = $conn->query($query);

    if ($result) {
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }

    return [];
}

// Get trimester content statistics
function getTrimesterContentStats()
{
    global $conn;

    $query = "
        SELECT 
            trimester,
            COUNT(*) as content_count,
            SUM(view_count) as total_views,
            AVG(view_count) as avg_views
        FROM content 
        GROUP BY trimester 
        ORDER BY trimester ASC
    ";

    $result = $conn->query($query);

    if ($result) {
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }

    return [];
}

// Get recent content views/activity
function getRecentContentViews($limit = 20)
{
    global $conn;

    $limit = intval($limit);

    $query = "
        SELECT 
            id,
            title,
            category,
            trimester,
            content_type,
            view_count,
            created_at,
            updated_at
        FROM content 
        ORDER BY updated_at DESC, view_count DESC
        LIMIT $limit
    ";

    $result = $conn->query($query);

    if ($result) {
        $content = [];
        while ($row = $result->fetch_assoc()) {
            $content[] = $row;
        }
        return $content;
    }

    return [];
}

// Get content performance by age group
function getAgeGroupStats()
{
    global $conn;

    $query = "
        SELECT 
            age_group,
            COUNT(*) as content_count,
            SUM(view_count) as total_views
        FROM content 
        GROUP BY age_group 
        ORDER BY total_views DESC
    ";

    $result = $conn->query($query);

    if ($result) {
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }

    return [];
}

// Get content type distribution
function getContentTypeStats()
{
    global $conn;

    $query = "
        SELECT 
            content_type,
            COUNT(*) as content_count,
            SUM(view_count) as total_views,
            AVG(view_count) as avg_views
        FROM content 
        GROUP BY content_type 
        ORDER BY content_count DESC
    ";

    $result = $conn->query($query);

    if ($result) {
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }

    return [];
}

// Get monthly content creation stats
function getMonthlyContentStats($months = 12)
{
    global $conn;

    $query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as content_created,
            SUM(view_count) as total_views
        FROM content 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ";

    $result = $conn->query($query);

    if ($result) {
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }

    return [];
}

// Get low performing content (needs attention)
function getLowPerformingContent($limit = 10)
{
    global $conn;

    $limit = intval($limit);

    $query = "
        SELECT 
            id,
            title,
            category,
            trimester,
            content_type,
            view_count,
            created_at,
            DATEDIFF(NOW(), created_at) as days_old
        FROM content 
        WHERE DATEDIFF(NOW(), created_at) > 7  -- At least a week old
        ORDER BY view_count ASC, created_at DESC
        LIMIT $limit
    ";

    $result = $conn->query($query);

    if ($result) {
        $content = [];
        while ($row = $result->fetch_assoc()) {
            $content[] = $row;
        }
        return $content;
    }

    return [];
}


?>