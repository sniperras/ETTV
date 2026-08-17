<?php
// test_session.php - Test if sessions are working
require_once 'config/db.php';

echo "<h2>Session Test</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session ID:</h3>";
echo session_id();

echo "<h3>CSRF Token:</h3>";
if (isset($_SESSION['csrf_token'])) {
    echo htmlspecialchars($_SESSION['csrf_token']);
} else {
    echo "No CSRF token set";
}

echo "<h3>Generate New CSRF Token:</h3>";
$new_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $new_token;
$_SESSION['csrf_token_time'] = time();
echo "New token generated: " . htmlspecialchars($new_token);

echo "<h3><a href='lmt/lmtadmin.php'>Go to LMT Admin</a></h3>";
?>