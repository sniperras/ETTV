<?php
// check_version.php - Simple polling endpoint
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config/db.php';

// Get parameters
$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
$current_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

// Validate mode
if (!in_array($mode, ['lmt', 'bmt'])) {
    echo json_encode(['error' => 'Invalid mode', 'has_update' => false]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
    $stmt->execute([$mode]);
    $result = $stmt->fetch();
    
    $latest_version = $result ? (int)$result['version'] : 1;
    
    echo json_encode([
        'has_update' => $latest_version > $current_version,
        'new_version' => $latest_version,
        'current_version' => $current_version
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'has_update' => false
    ]);
}
?>