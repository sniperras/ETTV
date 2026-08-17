<?php
require_once 'config/db.php';

echo "<h2>Debug - Current Active Content</h2>";

// Get active content
$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = 'lmt' AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$content = $stmt->fetch();

if ($content) {
    echo "<h3>Active Content:</h3>";
    echo "<pre>";
    print_r($content);
    echo "</pre>";
    
    echo "<h3>Content Data Parsed:</h3>";
    $data = json_decode($content['content_data'], true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    
    // Get slides if any
    if ($content['content_type'] === 'slideshow') {
        $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
        $stmt2->execute([$content['id']]);
        $slides = $stmt2->fetchAll();
        
        echo "<h3>Slides from content_slides table:</h3>";
        echo "<pre>";
        print_r($slides);
        echo "</pre>";
    }
} else {
    echo "<p style='color:red'>No active content found!</p>";
}

echo "<h2>All Content (last 5 entries):</h2>";
$stmt = $pdo->prepare("SELECT id, admin_role, content_type, is_active, created_at FROM content ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$all = $stmt->fetchAll();

echo "<pre>";
print_r($all);
echo "</pre>";

echo "<h2>Default Settings:</h2>";
$stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = 'lmt'");
$stmt->execute();
$default = $stmt->fetch();
echo "<pre>";
print_r($default);
echo "</pre>";
?>