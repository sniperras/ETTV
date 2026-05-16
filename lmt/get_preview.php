<?php
// lmt/get_preview.php - Preview endpoint for order manager
header('Content-Type: application/json');

// Find config
$paths = [
    '../config/db.php',
    __DIR__ . '/../config/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/ettv/config/db.php'
];

$found = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $found = true;
        break;
    }
}

if (!$found || !isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
$stmt->execute([$id]);
$content = $stmt->fetch();

if (!$content) {
    echo json_encode(['success' => false, 'error' => 'Content not found']);
    exit();
}

$slides = [];
if ($content['content_type'] === 'slideshow') {
    $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
    $stmt2->execute([$id]);
    $slides = $stmt2->fetchAll();
}

// Fix image paths to be absolute
foreach ($slides as &$slide) {
    if (!preg_match('/^https?:\/\//', $slide['image_path'])) {
        $slide['image_path'] = '/ettv/' . ltrim($slide['image_path'], '/');
    }
}

echo json_encode([
    'success' => true,
    'content' => $content,
    'slides' => $slides
]);
