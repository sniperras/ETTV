<?php
// sse_updates.php - Fixed version with proper connection handling
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

require_once 'config/db.php';

// Important: Set these for proper connection handling
set_time_limit(0);
ignore_user_abort(true);

// Start output buffering if not already started
if (ob_get_level() == 0) {
    ob_start();
}

$last_versions = ['lmt' => 0, 'bmt' => 0];
$heartbeat_counter = 0;

while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        error_log("SSE: Client disconnected");
        break;
    }

    $has_update = false;

    foreach (['lmt', 'bmt'] as $role) {
        try {
            // Check if PDO connection is still alive, reconnect if needed
            if (!isset($pdo) || !$pdo) {
                require_once 'config/db.php';
            }

            $stmt = $pdo->prepare("SELECT version, UNIX_TIMESTAMP(last_update) as last_update FROM content_version WHERE admin_role = ?");
            $stmt->execute([$role]);
            $result = $stmt->fetch();

            if ($result) {
                if (!isset($last_versions[$role]) || $result['version'] > $last_versions[$role]) {
                    $last_versions[$role] = $result['version'];
                    $has_update = true;

                    $data = json_encode([
                        'mode' => $role,
                        'version' => $result['version'],
                        'timestamp' => time()
                    ]);

                    echo "data: {$data}\n\n";
                    ob_flush();
                    flush();
                }
            }
        } catch (Exception $e) {
            error_log("SSE Error for role {$role}: " . $e->getMessage());
        }
    }

    // Send heartbeat every 5 seconds to keep connection alive and detect disconnects
    $heartbeat_counter++;
    if ($heartbeat_counter >= 5) { // Every ~5 seconds (since usleep is 1 second)
        echo ": heartbeat\n\n";
        ob_flush();
        flush();
        $heartbeat_counter = 0;
    }

    // Sleep to prevent CPU overload
    usleep(1000000); // 1 second
}

// Cleanup on disconnect
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
