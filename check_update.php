<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$mode = $_GET['mode'];
$timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : 0;

// Get latest content update time
$stmt = $pdo->prepare("SELECT MAX(updated_at) as last_update FROM content WHERE admin_role = ? AND is_active = 1");
$stmt->execute([$mode]);
$result = $stmt->fetch();

$last_update = strtotime($result['last_update']);

echo json_encode([
    'updated' => $last_update > ($timestamp / 1000)
]);
