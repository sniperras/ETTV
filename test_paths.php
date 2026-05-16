<?php
// test_paths.php - Test all endpoints
echo "<h2>ET TV Path Test</h2>";

$base_url = 'http://localhost/ettv/';

$tests = [
    'Index Page' => $base_url . 'index.php',
    'Admin Login' => $base_url . 'admin/login.php',
    'Get Content API' => $base_url . 'get_content.php?mode=lmt',
    'SSE Updates' => $base_url . 'sse_updates.php',
    'Check Version API' => $base_url . 'check_version.php?mode=lmt&version=1'
];

echo "<ul>";
foreach ($tests as $name => $url) {
    echo "<li><strong>$name:</strong> <a href='$url' target='_blank'>$url</a></li>";
}
echo "</ul>";

// Test get_content.php response
echo "<h3>Testing get_content.php response:</h3>";
$response = file_get_contents($base_url . 'get_content.php?mode=lmt');
$data = json_decode($response, true);

echo "<pre>";
print_r($data);
echo "</pre>";

if (isset($data['success']) && $data['success']) {
    echo "<p style='color:green'>✓ API is working correctly</p>";
} else {
    echo "<p style='color:red'>✗ API returned error: " . ($data['error'] ?? 'Unknown error') . "</p>";
}
