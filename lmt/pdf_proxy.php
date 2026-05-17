<?php
// lmt/pdf_proxy.php - Serves PDF files securely
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
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
$stmt = $pdo->prepare("SELECT content_data FROM content WHERE id = ? AND content_type = 'ppt' AND admin_role = 'lmt'");
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

// Find the actual file on disk
$possible_paths = [
    __DIR__ . '/../' . ltrim($pdfPath, '/'),
    __DIR__ . '/../../' . ltrim($pdfPath, '/'),
    $_SERVER['DOCUMENT_ROOT'] . '/ettv/' . ltrim($pdfPath, '/'),
    $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pdfPath, '/')
];

$filePath = null;
foreach ($possible_paths as $path) {
    $real = realpath($path);
    if ($real && file_exists($real) && is_file($real)) {
        $filePath = $real;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    exit('PDF file not found on server');
}

// Security: ensure file is within uploads directory
$uploadsDir = realpath(__DIR__ . '/../uploads');
if (!$uploadsDir || strpos($filePath, $uploadsDir) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');
readfile($filePath);
exit();
