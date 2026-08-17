<?php
// test_db.php
require_once 'config/db.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p style='color:green'>✓ Database connected successfully!</p>";
    echo "<p>User count: " . $result['count'] . "</p>";

    // Check if admin users exist
    $stmt = $pdo->query("SELECT username, role FROM users");
    $users = $stmt->fetchAll();

    echo "<h3>Current Users:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>" . htmlspecialchars($user['username']) . " - " . htmlspecialchars($user['role']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<h3>Paths:</h3>";
echo "<ul>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Script Path: " . __FILE__ . "</li>";
echo "<li>Admin Login URL: <a href='/ettv/admin/login.php'>/ettv/admin/login.php</a></li>";
echo "</ul>";
