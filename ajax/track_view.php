<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';


// Set headers for a 1x1 transparent pixel
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 transparent GIF in base64
$pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Check if user is logged in and content_id is provided
if (isLoggedIn() && isset($_GET['content_id']) && !empty($_GET['content_id'])) {
    $content_id = (int) $_GET['content_id'];
    $user_id = getCurrentUser()['id'];

    try {
        // Check if user has viewed this content recently
        $recent_view_sql = "SELECT id FROM content_views 
                           WHERE content_id = ? AND user_id = ? 
                           AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $recent_view_stmt = $pdo->prepare($recent_view_sql);
        $recent_view_stmt->execute([$content_id, $user_id]);

        if ($recent_view_stmt->rowCount() == 0) {
            // Update view count and insert view record
            $pdo->beginTransaction();

            $update_sql = "UPDATE content SET view_count = view_count + 1 WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$content_id]);

            $insert_sql = "INSERT INTO content_views (content_id, user_id, viewed_at, ip_address, user_agent) 
                           VALUES (?, ?, NOW(), ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $content_id,
                $user_id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                'Fallback Tracker'
            ]);

            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Fallback view tracking error: " . $e->getMessage());
    }
}

// Output the 1x1 pixel
echo $pixel;
exit;
?>