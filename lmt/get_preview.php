<?php
// get_preview.php - Fixed for InfinityFree
header('Content-Type: application/json');

// Simple path detection for InfinityFree
$config_path = __DIR__ . '/config/db.php';
if (!file_exists($config_path)) {
    $config_path = __DIR__ . '/../config/db.php';
}
if (!file_exists($config_path)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit();
}

require_once $config_path;

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND admin_role = 'lmt'");
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

// Fix image paths - keep them relative, frontend will add domain
foreach ($slides as &$slide) {
    if (!empty($slide['image_path'])) {
        // Remove any existing domain or leading slashes for consistency
        $slide['image_path'] = ltrim($slide['image_path'], '/');
    }
}

// For PDF, return the ID so we can use proxy
if ($content['content_type'] === 'ppt') {
    $content['pdf_proxy_url'] = 'pdf_proxy.php?id=' . $content['id'];
}

echo json_encode([
    'success' => true,
    'content' => $content,
    'slides' => $slides
]);
