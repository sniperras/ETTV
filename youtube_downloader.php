<?php
// youtube_downloader.php - Fixed version
require_once 'config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_POST['url'])) {
    echo json_encode(['success' => false, 'error' => 'No URL provided']);
    exit;
}

$url = trim($_POST['url']);
$videoId = extractYouTubeId($url);

if (!$videoId) {
    echo json_encode(['success' => false, 'error' => 'Invalid YouTube URL']);
    exit;
}

// Create directory
$uploadDir = __DIR__ . '/uploads/youtube/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Cannot create upload directory']);
        exit;
    }
}

// Check if already downloaded
$existingFile = glob($uploadDir . $videoId . '_*.mp4');
if (!empty($existingFile) && file_exists($existingFile[0])) {
    $filename = basename($existingFile[0]);
    $fileUrl = '/uploads/youtube/' . $filename;
    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $filename,
        'message' => 'Video already downloaded',
        'size' => round(filesize($existingFile[0]) / 1024 / 1024, 2) . ' MB'
    ]);
    exit;
}

// Try multiple possible paths for yt-dlp
$possiblePaths = [
    __DIR__ . '/yt-dlp.exe',
    __DIR__ . '/yt-dlp',
    'C:\\xampp\\htdocs\\ettv\\yt-dlp.exe',
    'yt-dlp.exe'
];

$ytDlpPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $ytDlpPath = $path;
        break;
    }
}

if (!$ytDlpPath) {
    echo json_encode([
        'success' => false,
        'error' => 'yt-dlp not found. Please download yt-dlp.exe from https://github.com/yt-dlp/yt-dlp/releases and place in the root folder.'
    ]);
    exit;
}

$filename = $videoId . '_' . time() . '.mp4';
$outputPath = $uploadDir . $filename;

// Download command using yt-dlp - simplified for testing
$command = "\"$ytDlpPath\" -f mp4 -o \"$outputPath\" \"$url\" 2>&1";

exec($command, $output, $returnCode);

// Check if download was successful
if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 100000) {
    $fileUrl = '/uploads/youtube/' . $filename;
    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $filename,
        'message' => 'Video downloaded successfully',
        'size' => round(filesize($outputPath) / 1024 / 1024, 2) . ' MB'
    ]);
} else {
    // Clean up failed download
    if (file_exists($outputPath)) {
        @unlink($outputPath);
    }

    // Provide more detailed error
    $errorMsg = 'Download failed';
    if (!empty($output)) {
        $errorMsg .= ': ' . implode(' ', array_slice($output, 0, 3));
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'details' => $returnCode,
        'command' => $command // Remove this in production
    ]);
}

function extractYouTubeId($url)
{
    $patterns = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\?]+)/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return false;
}
