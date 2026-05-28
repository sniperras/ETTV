<?php
// check_files.php - Check uploaded files
$uploads_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
$pdf_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pdf/';
$videos_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/videos/';
$audio_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/audio/';

echo "<h2>File System Check</h2>";

// Check main uploads directory
echo "<h3>Main Uploads Directory: $uploads_dir</h3>";
if (is_dir($uploads_dir)) {
    echo "✅ Directory exists<br>";
    $files = scandir($uploads_dir);
    echo "Files in uploads:<br>";
    foreach ($files as $f) {
        if ($f != '.' && $f != '..') {
            echo " - " . $f . " (" . round(filesize($uploads_dir . $f) / 1024, 2) . " KB)<br>";
        }
    }
} else {
    echo "❌ Directory does NOT exist<br>";
    mkdir($uploads_dir, 0777, true);
    echo "Created directory<br>";
}

// Check PDF directory
echo "<h3>PDF Directory: $pdf_dir</h3>";
if (is_dir($pdf_dir)) {
    echo "✅ Directory exists<br>";
    $files = scandir($pdf_dir);
    echo "Files in pdf:<br>";
    foreach ($files as $f) {
        if ($f != '.' && $f != '..') {
            echo " - " . $f . " (" . round(filesize($pdf_dir . $f) / 1024, 2) . " KB)<br>";
        }
    }
} else {
    echo "❌ Directory does NOT exist<br>";
    mkdir($pdf_dir, 0777, true);
    echo "Created directory<br>";
}

// Check database records
echo "<h3>Database Records</h3>";
require_once 'config/db.php';

$stmt = $pdo->prepare("SELECT id, content_type, content_data, display_order FROM content WHERE content_type = 'pdf' OR content_type = 'ppt' ORDER BY id DESC LIMIT 10");
$stmt->execute();
$contents = $stmt->fetchAll();

if (count($contents) > 0) {
    foreach ($contents as $c) {
        echo "<strong>ID: {$c['id']}</strong> - Type: {$c['content_type']}<br>";
        echo "Content Data: " . htmlspecialchars($c['content_data']) . "<br>";
        echo "Display Order: {$c['display_order']}<br><br>";
    }
} else {
    echo "No PDF or PPT records found in database<br>";
}
?>