<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';


header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['views']) || !is_array($input['views'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$user_id = getCurrentUser()['id'];
$processed_count = 0;

try {
    $pdo->beginTransaction();

    foreach ($input['views'] as $view_data) {
        if (!isset($view_data['content_id']) || !isset($view_data['timestamp'])) {
            continue;
        }

        $content_id = (int) $view_data['content_id'];
        $timestamp = (int) $view_data['timestamp'];

        // Skip if timestamp is too old (more than 7 days)
        if ((time() - $timestamp / 1000) > 604800) {
            continue;
        }

        // Check if this view was already recorded
        $check_sql = "SELECT id FROM content_views 
                     WHERE content_id = ? AND user_id = ? 
                     AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$content_id, $user_id]);

        if ($check_stmt->rowCount() == 0) {
            // Update view count
            $update_sql = "UPDATE content SET view_count = view_count + 1 WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$content_id]);

            // Insert view record
            $insert_sql = "INSERT INTO content_views (content_id, user_id, viewed_at, ip_address, user_agent) 
                           VALUES (?, ?, NOW(), ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $content_id,
                $user_id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                'Batch Update'
            ]);

            $processed_count++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'processed_count' => $processed_count,
        'message' => 'Batch update completed'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    error_log("Batch view update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>