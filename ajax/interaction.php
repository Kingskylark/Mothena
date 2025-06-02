<?php

ob_start();

require_once '../includes/config.php';


ob_end_clean();


// Prevent any HTML output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

// Function to safely output JSON and exit
function outputJson($data)
{
    echo json_encode($data);
    exit;
}

// Include config file
if (!file_exists('../includes/config.php')) {
    outputJson(['success' => false, 'error' => 'Config file not found']);
}

require_once '../includes/config.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJson(['success' => false, 'error' => 'Invalid request method']);
}

// Check if user is logged in (except for views)
if (!isset($_SESSION['user_id']) && (!isset($_POST['interaction_type']) || $_POST['interaction_type'] !== 'view')) {
    outputJson(['success' => false, 'error' => 'User not logged in']);
}

// Validate request parameters
if (
    !isset($_POST['action']) || $_POST['action'] !== 'interact' ||
    !isset($_POST['content_id']) || !isset($_POST['interaction_type'])
) {
    outputJson(['success' => false, 'error' => 'Missing required parameters']);
}

$content_id = (int) $_POST['content_id'];
$interaction_type = $_POST['interaction_type'];
$user_id = $_SESSION['user_id'] ?? null;

// Validate interaction type
$valid_types = ['like', 'bookmark', 'view'];
if (!in_array($interaction_type, $valid_types)) {
    outputJson(['success' => false, 'error' => 'Invalid interaction type']);
}

// Create database connection
// Create database connection
if (!defined('DB_HOST') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_NAME')) {
    outputJson(['success' => false, 'error' => 'Database configuration missing']);
}

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    outputJson(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
}

// Set charset to prevent encoding issues
$conn->set_charset("utf8");



try {
    // Handle different interaction types
    switch ($interaction_type) {
        case 'like':
            $result = handleLike($conn, $content_id, $user_id);
            break;
        case 'bookmark':
            $result = handleBookmark($conn, $content_id, $user_id);
            break;
        case 'view':
            $result = handleView($conn, $content_id, $user_id);
            break;
        default:
            throw new Exception('Invalid interaction type');
    }

    // Get updated counts
    $counts = getCounts($conn, $content_id);

    outputJson([
        'success' => true,
        'action' => $result['action'],
        'counts' => $counts
    ]);

} catch (Exception $e) {
    outputJson(['success' => false, 'error' => 'Operation failed: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function handleLike($conn, $content_id, $user_id)
{
    // Check if user already liked this content
    $stmt = $conn->prepare("SELECT id FROM user_interactions WHERE content_id = ? AND user_id = ? AND interaction_type = 'like'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $content_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Remove like
        $stmt = $conn->prepare("DELETE FROM user_interactions WHERE content_id = ? AND user_id = ? AND interaction_type = 'like'");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $content_id, $user_id);
        $stmt->execute();
        $stmt->close();
        return ['action' => 'removed'];
    } else {
        // Add like
        $stmt = $conn->prepare("INSERT INTO user_interactions (content_id, user_id, interaction_type, created_at) VALUES (?, ?, 'like', NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $content_id, $user_id);
        $stmt->execute();
        $stmt->close();
        return ['action' => 'added'];
    }
}

function handleBookmark($conn, $content_id, $user_id)
{
    // Check if user already bookmarked this content
    $stmt = $conn->prepare("SELECT id FROM user_interactions WHERE content_id = ? AND user_id = ? AND interaction_type = 'bookmark'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $content_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Remove bookmark
        $stmt = $conn->prepare("DELETE FROM user_interactions WHERE content_id = ? AND user_id = ? AND interaction_type = 'bookmark'");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $content_id, $user_id);
        $stmt->execute();
        $stmt->close();
        return ['action' => 'removed'];
    } else {
        // Add bookmark
        $stmt = $conn->prepare("INSERT INTO user_interactions (content_id, user_id, interaction_type, created_at) VALUES (?, ?, 'bookmark', NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $content_id, $user_id);
        $stmt->execute();
        $stmt->close();
        return ['action' => 'added'];
    }
}

function handleView($conn, $content_id, $user_id)
{
    // For views, we always increment (no toggle behavior)
    // Only count one view per user per content (to prevent spam)
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id FROM user_interactions WHERE content_id = ? AND user_id = ? AND interaction_type = 'view'");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $content_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            $stmt = $conn->prepare("INSERT INTO user_interactions (content_id, user_id, interaction_type, created_at) VALUES (?, ?, 'view', NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ii", $content_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // For anonymous users, track by IP (optional - you might want to skip this)
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("SELECT id FROM user_interactions WHERE content_id = ? AND ip_address = ? AND interaction_type = 'view' AND DATE(created_at) = CURDATE()");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("is", $content_id, $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            $stmt = $conn->prepare("INSERT INTO user_interactions (content_id, user_id, interaction_type, ip_address, created_at) VALUES (?, NULL, 'view', ?, NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("is", $content_id, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }

    return ['action' => 'added'];
}

function getCounts($conn, $content_id)
{
    $counts = [];

    // Get like count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_interactions WHERE content_id = ? AND interaction_type = 'like'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts['like'] = $result->fetch_assoc()['count'];
    $stmt->close();

    // Get bookmark count (optional - you might not want to show this)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_interactions WHERE content_id = ? AND interaction_type = 'bookmark'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts['bookmark'] = $result->fetch_assoc()['count'];
    $stmt->close();

    // Get view count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_interactions WHERE content_id = ? AND interaction_type = 'view'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts['view'] = $result->fetch_assoc()['count'];
    $stmt->close();

    return $counts;
}
?>