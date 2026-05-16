<?php
// get_content.php - Final Production Version
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Error handling - convert all errors to JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0);

function jsonErrorHandler($errno, $errstr, $errfile, $errline)
{
    echo json_encode([
        'success' => false,
        'error' => "Error: $errstr in $errfile on line $errline"
    ]);
    exit();
}
set_error_handler('jsonErrorHandler');

function jsonExceptionHandler($e)
{
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
set_exception_handler('jsonExceptionHandler');

// Database connection - try multiple paths for compatibility
$paths_to_try = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(__DIR__) . '/config/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/ettv/config/db.php'
];

$db_loaded = false;
foreach ($paths_to_try as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded || !isset($pdo)) {
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration not found'
    ]);
    exit();
}

// Helper function to validate mode
function validateMode($mode)
{
    return in_array($mode, ['lmt', 'bmt']);
}

// Helper function to get slides for a content
function getSlides($pdo, $content_id)
{
    $slides = [];
    $stmt = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
    $stmt->execute([$content_id]);
    return $stmt->fetchAll();
}

try {
    // Handle direct content ID request (for next content and preview)
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
        $stmt->execute([$id]);
        $content = $stmt->fetch();

        if ($content) {
            $slides = [];
            if ($content['content_type'] === 'slideshow') {
                $slides = getSlides($pdo, $content['id']);
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
    }
    // Handle mode-based request (current content for TV display)
    elseif (isset($_GET['mode'])) {
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

        // Return default content
        if (isset($_GET['default'])) {
            $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
            $stmt->execute([$mode]);
            $default = $stmt->fetch();

            if (!$default) {
                $stmt = $pdo->prepare("INSERT INTO default_settings (admin_role, default_content_type, default_content_data, default_message_type) VALUES (?, 'message', 'Welcome to ET TV Display', 'memo')");
                $stmt->execute([$mode]);
                $default = [
                    'default_content_type' => 'message',
                    'default_content_data' => 'Welcome to ET TV Display',
                    'default_message_type' => 'memo'
                ];
            }

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
        // Return current active content
        else {
            $stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY COALESCE(display_order, id) ASC, created_at DESC LIMIT 1");
            $stmt->execute([$mode]);
            $content = $stmt->fetch();

            if ($content) {
                $slides = [];
                if ($content['content_type'] === 'slideshow') {
                    $slides = getSlides($pdo, $content['id']);
                }

                echo json_encode([
                    'success' => true,
                    'content' => $content,
                    'slides' => $slides
                ]);
            } else {
                // Return default content if no active content
                $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
                $stmt->execute([$mode]);
                $default = $stmt->fetch();

                if (!$default) {
                    $default = [
                        'default_content_type' => 'message',
                        'default_content_data' => 'Welcome to ET TV Display',
                        'default_message_type' => 'memo'
                    ];
                }

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
            'error' => 'Missing parameters. Please provide either "id" or "mode" parameter.',
            'content' => null,
            'slides' => []
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in get_content.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'content' => null,
        'slides' => []
    ]);
} catch (Exception $e) {
    error_log("General error in get_content.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'content' => null,
        'slides' => []
    ]);
}
