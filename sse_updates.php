<?php
// sse_updates.php - Optimized version with connection handling
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for nginx

require_once 'config/db.php';

// Disable time limit but check for connection aborts
set_time_limit(0);
ignore_user_abort(false);

$last_versions = ['lmt' => 0, 'bmt' => 0];

while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }

    foreach (['lmt', 'bmt'] as $role) {
        try {
            $stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
            $stmt->execute([$role]);
            $result = $stmt->fetch();

            if ($result && $result['version'] > $last_versions[$role]) {
                $last_versions[$role] = $result['version'];
                echo "data: " . json_encode([
                    'mode' => $role,
                    'version' => $result['version'],
                    'timestamp' => time()
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        } catch (Exception $e) {
            // Silently fail and continue
            error_log("SSE Error: " . $e->getMessage());
        }
    }

    // Sleep shorter for faster updates, but not CPU intensive
    usleep(500000); // 0.5 seconds
}

// Cleanup on disconnect
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
