<?php
// lmt/youtube_proxy.php - Fixed to download actual video
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

// Create directory
$uploadDir = __DIR__ . '/../uploads/youtube/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if already downloaded
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

// Use ytdl API service to get video URL
function getVideoDownloadUrl($videoId)
{
    // Method 1: Using y2mate API
    $apis = [
        "https://api.vevioz.com/api/button/mp4/{$videoId}",
        "https://p.oceansaver.in/ajax/download.php?format=mp4&url=https://youtube.com/watch?v={$videoId}"
    ];

    foreach ($apis as $apiUrl) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($data && isset($data['downloadUrl'])) {
            return $data['downloadUrl'];
        }
        if ($data && isset($data['vid'])) {
            return "https://v1.vevioz.com/@api/button/mp4/{$data['vid']}";
        }
    }

    return false;
}

// Alternative: Use direct YouTube download URLs (best quality)
function getDirectVideoUrl($videoId)
{
    // Try to get the video using invidious API
    $invidiousInstances = [
        'https://yewtu.be',
        'https://inv.riverside.rocks',
        'https://invidious.snopyta.org'
    ];

    foreach ($invidiousInstances as $instance) {
        $apiUrl = $instance . "/api/v1/videos/{$videoId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($data && isset($data['formatStreams'])) {
            // Get the best quality mp4
            foreach ($data['formatStreams'] as $format) {
                if ($format['ext'] === 'mp4' && isset($format['url'])) {
                    return $format['url'];
                }
            }
        }
    }

    return false;
}

// Try to get download URL
$downloadUrl = getDirectVideoUrl($videoId);
if (!$downloadUrl) {
    $downloadUrl = getVideoDownloadUrl($videoId);
}

if (!$downloadUrl) {
    echo json_encode([
        'success' => false,
        'error' => 'Could not get download URL. YouTube may have blocked this request.'
    ]);
    exit;
}

// Download the actual video
$filename = $videoId . '_' . time() . '.mp4';
$filepath = $uploadDir . $filename;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
$videoData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Check if we got actual video data (not HTML or image)
if ($httpCode === 200 && $videoData && strlen($videoData) > 100000 && strpos($contentType, 'video/') !== false) {
    file_put_contents($filepath, $videoData);
    $fileUrl = '/uploads/youtube/' . $filename;

    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $filename,
        'message' => 'Video downloaded successfully',
        'size' => round(strlen($videoData) / 1024 / 1024, 2) . ' MB'
    ]);
} else {
    // Clean up failed download
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    echo json_encode([
        'success' => false,
        'error' => 'Download failed - got ' . ($contentType ?: 'unknown') . ' instead of video',
        'details' => 'HTTP Code: ' . $httpCode . ', Content-Type: ' . ($contentType ?: 'unknown')
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
