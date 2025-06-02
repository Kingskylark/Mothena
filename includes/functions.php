<?php
require_once 'config.php';

// Get personalized content based on user profile
function getPersonalizedContent($user_id, $limit = 10)
{
    global $conn;

    // Get user profile
    $user_query = "SELECT * FROM users WHERE id = $user_id";
    $user_result = $conn->query($user_query);
    $user = $user_result->fetch_assoc();

    if (!$user)
        return [];

    // Get user interests as array
    $interests = explode(',', $user['interests']);
    $interests = array_map('trim', $interests);

    // Build query for personalized content
    $where_conditions = [];
    $where_conditions[] = "trimester = " . $user['trimester'];

    // Add age group filtering
    $age_group = getAgeGroup($user['age']);
    $where_conditions[] = "(age_group = '$age_group' OR age_group = 'all')";

    // Add interest-based filtering
    if (!empty($interests)) {
        $interest_conditions = [];
        foreach ($interests as $interest) {
            $interest_conditions[] = "category = '$interest'";
        }
        $where_conditions[] = "(" . implode(' OR ', $interest_conditions) . ")";
    }

    $where_clause = implode(' AND ', $where_conditions);

    $query = "SELECT *, 
              CASE 
                WHEN is_featured = 1 THEN 3
                WHEN category IN ('" . implode("','", $interests) . "') THEN 2
                ELSE 1
              END as priority_score
              FROM content 
              WHERE $where_clause 
              ORDER BY priority_score DESC, created_at DESC 
              LIMIT $limit";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get personalized tips
function getPersonalizedTips($user_id, $limit = 5)
{
    global $conn;

    $user_query = "SELECT * FROM users WHERE id = $user_id";
    $user_result = $conn->query($user_query);
    $user = $user_result->fetch_assoc();

    if (!$user)
        return [];

    $interests = explode(',', $user['interests']);
    $interests = array_map('trim', $interests);
    $age_group = getAgeGroup($user['age']);

    $query = "SELECT * FROM content 
              WHERE trimester = " . $user['trimester'] . " 
              AND content_type = 'tip' 
              AND (age_group = '$age_group' OR age_group = 'all')
              ORDER BY RAND() 
              LIMIT $limit";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get personalized reminders
function getPersonalizedReminders($user_id, $limit = 5)
{
    global $conn;

    $query = "SELECT * FROM reminders 
              WHERE user_id = $user_id 
              AND due_date >= CURDATE() 
              AND is_completed = FALSE 
              ORDER BY due_date ASC 
              LIMIT $limit";

    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Determine age group based on age
function getAgeGroup($age)
{
    if ($age < 20)
        return 'teen';
    if ($age < 25)
        return 'young_adult';
    if ($age < 35)
        return 'adult';
    if ($age < 40)
        return 'mature_adult';
    return 'senior_adult';
}

// Track user interaction with content
function trackInteraction($user_id, $content_id, $interaction_type)
{
    global $conn;

    $query = "INSERT INTO user_interactions (user_id, content_id, interaction_type) 
              VALUES ($user_id, $content_id, '$interaction_type')";

    return $conn->query($query);
}

// Update content view count
function updateViewCount($content_id)
{
    global $conn;

    $query = "UPDATE content SET view_count = view_count + 1 WHERE id = $content_id";
    return $conn->query($query);
}

// Get user's pregnancy week
function getPregnancyWeek($due_date)
{
    $due = new DateTime($due_date);
    $now = new DateTime();

    // Calculate weeks pregnant (40 weeks total pregnancy)
    $conception_date = clone $due;
    $conception_date->sub(new DateInterval('P280D')); // 40 weeks = 280 days

    $interval = $now->diff($conception_date);
    $weeks = floor($interval->days / 7);

    return max(0, min(40, $weeks));
}

// Validate email format
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate secure password hash
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash)
{
    return md5($password, $hash);
}
?>

<?php
// MySQLi versions of your functions

function getContentViewCount($content_id)
{
    global $conn;

    $content_id = intval($content_id); // Sanitize input
    $query = "SELECT COUNT(*) as view_count FROM content_views WHERE content_id = $content_id";
    $result = $conn->query($query);

    if ($result && $row = $result->fetch_assoc()) {
        return (int) $row['view_count'];
    }
    return 0;
}

function getPersonalizedContentWithViews($user_id, $limit = 20)
{
    global $conn;

    $user_id = intval($user_id);
    $limit = intval($limit);

    $query = "
        SELECT c.*, 
               (SELECT COUNT(*) FROM content_views cv WHERE cv.content_id = c.id) as actual_view_count
        FROM content c 
        ORDER BY c.is_featured DESC, c.created_at DESC 
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

function getUniqueContentViewCount($content_id)
{
    global $conn;

    $content_id = intval($content_id);
    $query = "SELECT COUNT(DISTINCT user_id) as unique_view_count FROM content_views WHERE content_id = $content_id";
    $result = $conn->query($query);

    if ($result && $row = $result->fetch_assoc()) {
        return (int) $row['unique_view_count'];
    }
    return 0;
}

function getPersonalizedContentWithViewsSummary($user_id, $limit = 20)
{
    global $conn;

    $user_id = intval($user_id);
    $limit = intval($limit);

    $query = "
        SELECT c.*, 
               COALESCE(cvs.total_views, 0) as actual_view_count,
               COALESCE(cvs.unique_viewers, 0) as unique_viewers,
               COALESCE(cvs.views_24h, 0) as views_today,
               COALESCE(cvs.views_7d, 0) as views_week,
               cvs.last_viewed
        FROM content c 
        LEFT JOIN content_view_summary cvs ON c.id = cvs.content_id
        WHERE c.is_active = 1 
        ORDER BY c.is_featured DESC, c.created_at DESC 
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

    // Fallback to basic function
    return getPersonalizedContentWithViews($user_id, $limit);
}

function getTrendingContent($limit = 10, $days = 7)
{
    global $conn;

    $limit = intval($limit);
    $days = intval($days);

    $query = "
        SELECT c.*, 
               COALESCE(vcv.total_views, 0) as actual_view_count,
               COALESCE(vcv.unique_viewers, 0) as unique_viewers,
               COALESCE(vcv.views_7d, 0) as recent_views
        FROM content c 
        LEFT JOIN content_view_counts vcv ON c.id = vcv.content_id
        WHERE c.is_active = 1 
        ORDER BY vcv.views_7d DESC, vcv.total_views DESC, c.created_at DESC
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

function getContentAnalytics($content_id = null)
{
    global $conn;

    if ($content_id) {
        $content_id = intval($content_id);
        $query = "
            SELECT 
                c.title,
                c.content_type,
                c.category,
                COALESCE(vcv.total_views, 0) as total_views,
                COALESCE(vcv.unique_viewers, 0) as unique_viewers,
                COALESCE(vcv.views_24h, 0) as views_24h,
                COALESCE(vcv.views_7d, 0) as views_7d,
                COALESCE(vcv.views_30d, 0) as views_30d,
                vcv.last_viewed
            FROM content c 
            LEFT JOIN content_view_counts vcv ON c.id = vcv.content_id
            WHERE c.id = $content_id
        ";

        $result = $conn->query($query);
        return $result ? $result->fetch_assoc() : null;
    } else {
        $query = "
            SELECT 
                COUNT(DISTINCT cv.content_id) as total_content_with_views,
                COUNT(*) as total_views,
                COUNT(DISTINCT cv.user_id) as total_unique_viewers,
                COUNT(CASE WHEN cv.viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as views_24h,
                COUNT(CASE WHEN cv.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as views_7d,
                COUNT(CASE WHEN cv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as views_30d
            FROM content_views cv
        ";

        $result = $conn->query($query);
        return $result ? $result->fetch_assoc() : null;
    }
}

function hasUserViewedRecently($content_id, $user_id, $hours = 24)
{
    global $conn;

    $content_id = intval($content_id);
    $user_id = intval($user_id);
    $hours = intval($hours);

    $query = "
        SELECT id FROM content_views 
        WHERE content_id = $content_id AND user_id = $user_id 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
        LIMIT 1
    ";

    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}



/**
 * Get view statistics for a content item
 */
function getContentViewStats($content_id)
{
    global $pdo;

    try {
        $sql = "SELECT 
                    c.view_count,
                    COUNT(DISTINCT cv.user_id) as unique_viewers,
                    COUNT(cv.id) as total_views_logged
                FROM content c
                LEFT JOIN content_views cv ON c.id = cv.content_id
                WHERE c.id = ?
                GROUP BY c.id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$content_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting view stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Get most viewed content
 */
function getMostViewedContent($limit = 10, $trimester = null, $category = null)
{
    global $pdo;

    try {
        $sql = "SELECT * FROM content WHERE 1=1";
        $params = [];

        if ($trimester) {
            $sql .= " AND trimester = ?";
            $params[] = $trimester;
        }

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY view_count DESC LIMIT ?";
        $params[] = (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting most viewed content: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has viewed specific content
 */
function hasUserViewedContent($user_id, $content_id)
{
    global $pdo;

    try {
        $sql = "SELECT id FROM content_views 
                WHERE user_id = ? AND content_id = ? 
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $content_id]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking user view: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's recently viewed content
 */
function getUserRecentlyViewed($user_id, $limit = 10)
{
    global $pdo;

    try {
        $sql = "SELECT c.*, cv.viewed_at as last_viewed
                FROM content c
                INNER JOIN content_views cv ON c.id = cv.content_id
                WHERE cv.user_id = ?
                ORDER BY cv.viewed_at DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, (int) $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recently viewed: " . $e->getMessage());
        return [];
    }
}


// Add these functions to your functions.php file

// Function to track user interactions
function trackUserInteraction($user_id, $content_id, $interaction_type)
{
    global $conn;

    // Check if interaction already exists (for likes/bookmarks)
    if ($interaction_type == 'like' || $interaction_type == 'bookmark') {
        $check_stmt = $conn->prepare("SELECT id FROM user_interactions WHERE user_id = ? AND content_id = ? AND interaction_type = ?");
        $check_stmt->bind_param("iis", $user_id, $content_id, $interaction_type);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            // Remove interaction if it exists (toggle functionality)
            $delete_stmt = $conn->prepare("DELETE FROM user_interactions WHERE user_id = ? AND content_id = ? AND interaction_type = ?");
            $delete_stmt->bind_param("iis", $user_id, $content_id, $interaction_type);
            $delete_stmt->execute();
            $delete_stmt->close();
            $check_stmt->close();
            return 'removed';
        }
        $check_stmt->close();
    }

    // Add new interaction
    $stmt = $conn->prepare("INSERT INTO user_interactions (user_id, content_id, interaction_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $content_id, $interaction_type);
    $result = $stmt->execute();
    $stmt->close();

    return $result ? 'added' : false;
}

// Function to get user's interactions for specific content
function getUserInteractions($user_id, $content_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT interaction_type FROM user_interactions WHERE user_id = ? AND content_id = ?");
    $stmt->bind_param("ii", $user_id, $content_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $interactions = array();
    while ($row = $result->fetch_assoc()) {
        $interactions[] = $row['interaction_type'];
    }

    $stmt->close();
    return $interactions;
}

// Function to get interaction counts for content
function getInteractionCounts($content_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            interaction_type, 
            COUNT(*) as count 
        FROM user_interactions 
        WHERE content_id = ? 
        GROUP BY interaction_type
    ");
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = array(
        'view' => 0,
        'like' => 0,
        'bookmark' => 0,
        'share' => 0
    );

    while ($row = $result->fetch_assoc()) {
        $counts[$row['interaction_type']] = $row['count'];
    }

    $stmt->close();
    return $counts;
}

// Function to get user's bookmarked content
function getUserBookmarks($user_id, $limit = 10)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT c.*, ui.created_at as bookmarked_at
        FROM content c
        JOIN user_interactions ui ON c.id = ui.content_id
        WHERE ui.user_id = ? AND ui.interaction_type = 'bookmark'
        ORDER BY ui.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookmarks = array();
    while ($row = $result->fetch_assoc()) {
        $bookmarks[] = $row;
    }

    $stmt->close();
    return $bookmarks;
}

// AJAX handler for interactions (create a new file: ajax/interaction.php)
if (isset($_POST['action']) && $_POST['action'] == 'interact') {
    session_start();
    require_once '../includes/config.php';
    require_once '../includes/functions.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $content_id = (int) $_POST['content_id'];
    $interaction_type = $_POST['interaction_type'];

    // Validate interaction type
    $valid_types = ['view', 'like', 'bookmark', 'share'];
    if (!in_array($interaction_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid interaction type']);
        exit;
    }

    $result = trackUserInteraction($user_id, $content_id, $interaction_type);
    $counts = getInteractionCounts($content_id);

    echo json_encode([
        'success' => true,
        'action' => $result,
        'counts' => $counts
    ]);
}
?>