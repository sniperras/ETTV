<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$mode = $_GET['mode'];
$current_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

$stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
$stmt->execute([$mode]);
$result = $stmt->fetch();

$latest_version = $result ? $result['version'] : 1;

echo json_encode([
    'has_update' => $latest_version > $current_version,
    'new_version' => $latest_version
]);
