<?php
// lmt/pdf_proxy.php - Serves PDF files securely for InfinityFree
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

// Get the file path from database (LMT uses 'pdf', legacy entries may use 'ppt')
$stmt = $pdo->prepare("SELECT content_data FROM content WHERE id = ? AND content_type IN ('pdf', 'ppt') AND admin_role = 'lmt'");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('PDF not found');
}

require_once __DIR__ . '/../includes/pdf_file_resolver.php';

$pdfPath = getPdfPathFromContentData($row['content_data']);
$filePath = resolvePdfFileOnDisk($pdfPath);

if (!$filePath) {
    http_response_code(404);
    exit('PDF file not found on server');
}

servePdfFile($filePath);
?>