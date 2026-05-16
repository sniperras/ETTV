<?php
// get_content.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config/db.php';

try {
    // Validate mode parameter
    function validateMode($mode)
    {
        return in_array($mode, ['lmt', 'bmt']);
    }

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $content = $stmt->fetch();

        if ($content) {
            $slides = [];
            if ($content['content_type'] === 'slideshow') {
                $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
                $stmt2->execute([$content['id']]);
                $slides = $stmt2->fetchAll();
            }

            echo json_encode([
                'success' => true,
                'content' => $content,
                'slides' => $slides
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Content not found',
                'content' => null,
                'slides' => []
            ]);
        }
    } elseif (isset($_GET['mode'])) {
        $mode = $_GET['mode'];

        if (!validateMode($mode)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid mode',
                'content' => null,
                'slides' => []
            ]);
            exit();
        }

        if (isset($_GET['default'])) {
            $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
            $stmt->execute([$mode]);
            $default = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'content' => [
                    'id' => null,
                    'content_type' => $default['default_content_type'],
                    'content_data' => $default['default_content_data'],
                    'message_type' => $default['default_message_type'],
                    'display_duration' => 10,
                    'loop_count' => 1,
                    'next_content_id' => null
                ],
                'slides' => []
            ]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$mode]);
            $content = $stmt->fetch();

            if ($content) {
                $slides = [];
                if ($content['content_type'] === 'slideshow') {
                    $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
                    $stmt2->execute([$content['id']]);
                    $slides = $stmt2->fetchAll();
                }

                echo json_encode([
                    'success' => true,
                    'content' => $content,
                    'slides' => $slides
                ]);
            } else {
                // Return default content
                $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
                $stmt->execute([$mode]);
                $default = $stmt->fetch();

                echo json_encode([
                    'success' => true,
                    'content' => [
                        'id' => null,
                        'content_type' => $default['default_content_type'],
                        'content_data' => $default['default_content_data'],
                        'message_type' => $default['default_message_type'],
                        'display_duration' => 10,
                        'loop_count' => 1,
                        'next_content_id' => null
                    ],
                    'slides' => []
                ]);
            }
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Missing parameters',
            'content' => null,
            'slides' => []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'content' => null,
        'slides' => []
    ]);
}
