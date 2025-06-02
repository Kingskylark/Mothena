<?php

ob_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';

ob_end_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if content_id is provided
if (!isset($_POST['content_id']) || empty($_POST['content_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Content ID is required']);
    exit;
}

$content_id = (int) $_POST['content_id'];
$user = getCurrentUser();
$user_id = $user['id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User ID missing']);
    exit;
}

// Check if content exists
$check_sql = "SELECT id, view_count FROM content WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $content_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$content = $result->fetch_assoc();

if (!$content) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Content not found']);
    exit;
}

// Check if user has already viewed this content in the last 24 hours
$recent_view_sql = "SELECT id FROM content_views 
                    WHERE content_id = ? AND user_id = ? 
                    AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$recent_stmt = $conn->prepare($recent_view_sql);
$recent_stmt->bind_param("ii", $content_id, $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();

if ($recent_result->num_rows === 0) {
    // No recent view: increment and log

    $conn->begin_transaction();

    try {
        // Update content view count
        $update_sql = "UPDATE content SET view_count = view_count + 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $content_id);
        $update_stmt->execute();

        // Insert into content_views
        $insert_sql = "INSERT INTO content_views (content_id, user_id, viewed_at, ip_address, user_agent)
                       VALUES (?, ?, NOW(), ?, ?)";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiss", $content_id, $user_id, $ip, $ua);
        $insert_stmt->execute();

        $conn->commit();

        // Return new count
        $new_count = $content['view_count'] + 1;

        echo json_encode([
            'success' => true,
            'message' => 'View count updated successfully',
            'new_count' => $new_count
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("View count update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    // Already viewed
    echo json_encode([
        'success' => true,
        'message' => 'View already recorded recently',
        'new_count' => $content['view_count']
    ]);
}
?>