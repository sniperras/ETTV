<?php
// lmt/youtube_downloader.php
require_once '../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

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

// Create directory if not exists
$uploadDir = __DIR__ . '/../uploads/youtube/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if video already downloaded
$existingFile = glob($uploadDir . $videoId . '_*.mp4');
if (!empty($existingFile)) {
    $filename = basename($existingFile[0]);
    $fileUrl = '/uploads/youtube/' . $filename;
    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $filename,
        'message' => 'Video already downloaded'
    ]);
    exit;
}

$filename = $videoId . '_' . time() . '.mp4';
$outputPath = $uploadDir . $filename;

// Try yt-dlp first
$ytDlpPath = '/usr/local/bin/yt-dlp';
if (file_exists($ytDlpPath)) {
    $command = "{$ytDlpPath} -f 'best[ext=mp4]' " .
               "--no-warnings " .
               "-o " . escapeshellarg($outputPath) . " " .
               escapeshellarg($url) . " 2>&1";
} else {
    // Try youtube-dl as fallback
    $ytDlpPath = '/usr/bin/youtube-dl';
    if (file_exists($ytDlpPath)) {
        $command = "{$ytDlpPath} -f 'best[ext=mp4]' " .
                   "--no-warnings " .
                   "-o " . escapeshellarg($outputPath) . " " .
                   escapeshellarg($url) . " 2>&1";
    } else {
        echo json_encode(['success' => false, 'error' => 'No downloader available. Please install yt-dlp.']);
        exit;
    }
}

exec($command, $output, $returnCode);

if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
    $fileUrl = '/uploads/youtube/' . $filename;
    
    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $filename,
        'message' => 'Video downloaded successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Download failed',
        'details' => implode("\n", $output)
    ]);
}

function extractYouTubeId($url) {
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
?>