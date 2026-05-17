<?php
// bmt/pdf_proxy.php - Serves PDF files securely for InfinityFree
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bmtadmin') {
    http_response_code(403);
    exit('Forbidden');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(400);
    exit('No ID provided');
}

// Database connection
$config_path = __DIR__ . '/../config/db.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    exit('Config not found');
}
require_once $config_path;

if (!isset($pdo)) {
    http_response_code(500);
    exit('Database connection failed');
}

// Get the file path from database
$stmt = $pdo->prepare("SELECT content_data FROM content WHERE id = ? AND content_type = 'ppt' AND admin_role = 'bmt'");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('PDF not found');
}

// Parse content_data (could be JSON or direct path)
$pdfPath = $row['content_data'];
$data = json_decode($pdfPath, true);
if ($data && isset($data['file_path'])) {
    $pdfPath = $data['file_path'];
}

// Get just the filename
$filename = basename($pdfPath);

// On InfinityFree, files are in /htdocs/uploads/
$possible_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $filename,
    __DIR__ . '/../uploads/' . $filename,
    '/home/vol*/if0_*/htdocs/uploads/' . $filename  // Wildcard for InfinityFree
];

$filePath = null;
foreach ($possible_paths as $path) {
    // Use glob for wildcard paths
    $globbed = glob($path);
    if ($globbed && !empty($globbed) && file_exists($globbed[0])) {
        $filePath = $globbed[0];
        break;
    }
    if (file_exists($path)) {
        $filePath = $path;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    exit('PDF file not found on server');
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');
readfile($filePath);
exit();
