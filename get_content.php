<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND is_active = 1");
    $stmt->execute([$_GET['id']]);
    $content = $stmt->fetch();

    if ($content) {
        echo json_encode($content);
    } else {
        echo json_encode(['error' => 'Content not found']);
    }
} elseif (isset($_GET['mode'])) {
    $mode = $_GET['mode'];

    if (isset($_GET['default'])) {
        $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
        $stmt->execute([$mode]);
        $default = $stmt->fetch();
        echo json_encode([
            'content_type' => $default['default_content_type'],
            'content_data' => $default['default_content_data'],
            'message_type' => $default['default_message_type'],
            'display_duration' => 10,
            'loop_count' => 1
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$mode]);
        $content = $stmt->fetch();

        if ($content) {
            echo json_encode($content);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
            $stmt->execute([$mode]);
            $default = $stmt->fetch();
            echo json_encode([
                'content_type' => $default['default_content_type'],
                'content_data' => $default['default_content_data'],
                'message_type' => $default['default_message_type'],
                'display_duration' => 10,
                'loop_count' => 1
            ]);
        }
    }
}
