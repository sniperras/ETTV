<?php
// Public PDF endpoint for TV display (InfinityFree blocks direct /uploads/ access)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/pdf_file_resolver.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'lmt';

if (!$id) {
    http_response_code(400);
    exit('No ID provided');
}

if (!in_array($mode, ['lmt', 'bmt'], true)) {
    http_response_code(400);
    exit('Invalid mode');
}

$stmt = $pdo->prepare(
    "SELECT content_data FROM content
     WHERE id = ? AND admin_role = ? AND content_type IN ('pdf', 'ppt') AND is_active = 1"
);
$stmt->execute([$id, $mode]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('PDF not found');
}

$pdfPath = getPdfPathFromContentData($row['content_data']);
$filePath = resolvePdfFileOnDisk($pdfPath);

if (!$filePath) {
    http_response_code(404);
    exit('PDF file not found on server');
}

servePdfFile($filePath);
