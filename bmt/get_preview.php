<?php
// get_preview.php - Fixed for InfinityFree with correct paths
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

$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND admin_role = 'bmt'");
$stmt->execute([$id]);
$content = $stmt->fetch();

if (!$content) {
    echo json_encode(['success' => false, 'error' => 'Content not found']);
    exit();
}

$slides = [];
if ($content['content_type'] === 'slideshow') {
    // First try to get from content_slides table
    $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
    $stmt2->execute([$id]);
    $slides = $stmt2->fetchAll();

    // If no slides in table, try to extract from content_data JSON (for multi-image layouts)
    if (empty($slides)) {
        $data = json_decode($content['content_data'], true);
        if ($data && isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $img) {
                $slides[] = ['image_path' => $img['path'], 'duration' => $img['duration'] ?? 10];
            }
        }
    }
}

// Fix image paths - InfinityFree uses /htdocs/uploads/
foreach ($slides as &$slide) {
    if (!empty($slide['image_path'])) {
        // Get just the filename
        $filename = basename($slide['image_path']);
        $slide['image_path'] = '/uploads/' . $filename;
    }
}

// For PDF, return the proxy URL
if ($content['content_type'] === 'ppt') {
    $content['pdf_proxy_url'] = '/bmt/pdf_proxy.php?id=' . $content['id'];
}

echo json_encode([
    'success' => true,
    'content' => $content,
    'slides' => $slides
]);
