<?php
// admin/admin.php - Admin Dashboard
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../config/db.php';

// Add the formatDuration function here
function formatDuration($seconds)
{
    if ($seconds < 60) return $seconds . ' seconds';
    if ($seconds < 3600) return round($seconds / 60) . ' minutes';
    if ($seconds < 86400) return round($seconds / 3600) . ' hours';
    return round($seconds / 86400) . ' days';
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role != 'admin'");
    $stmt->execute([$new_password, $user_id]);

    $_SESSION['flash_success'] = "Password reset successfully for user ID: " . $user_id;
    header('Location: admin.php');
    exit();
}

// Handle add new user
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = "Username already exists!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
        $_SESSION['flash_success'] = "User '$username' added successfully!";
    }
    header('Location: admin.php');
    exit();
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $user_id = (int)$_GET['delete_user'];
    // Don't allow deleting yourself
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        $_SESSION['flash_success'] = "User deleted successfully!";
    } else {
        $_SESSION['flash_error'] = "Cannot delete your own account!";
    }
    header('Location: admin.php');
    exit();
}

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY id ASC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get activity logs with user information
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.role 
    FROM content c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.admin_role = 'lmt' 
    ORDER BY c.updated_at DESC 
    LIMIT 50
");
$stmt->execute();
$activities = $stmt->fetchAll();

// Get content stats
$stmt = $pdo->prepare("SELECT content_type, COUNT(*) as count FROM content WHERE admin_role = 'lmt' GROUP BY content_type");
$stmt->execute();
$content_stats = $stmt->fetchAll();

// Get total content count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM content WHERE admin_role = 'lmt'");
$stmt->execute();
$total_content = $stmt->fetch()['total'];

// Get active content count
$stmt = $pdo->prepare("SELECT COUNT(*) as active FROM content WHERE admin_role = 'lmt' AND is_active = 1");
$stmt->execute();
$active_content = $stmt->fetch()['active'];

// Check for errors in logs (safe way - no file_exists issue)
$error_logs = [];
$log_file = ini_get('error_log');
if ($log_file && $log_file !== '/dev/null' && file_exists($log_file)) {
    $lines = @file($log_file);
    if ($lines !== false) {
        $error_logs = array_slice($lines, -20); // Last 20 lines
    }
}

$success = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ET TV</title>
    <link rel="icon" type="image/png" href="../img/ethiopian_logo.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            font-size: 24px;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .card h2 .badge {
            font-size: 12px;
            background: #667eea;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            margin-left: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .modal .form-group {
            margin-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        .modal input,
        .modal select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .modal input:focus,
        .modal select:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons .btn {
            flex: 1;
            text-align: center;
            padding: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .fade-out {
            animation: fadeOut 0.5s ease forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
                display: none;
            }
        }

        .role-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .role-admin {
            background: #667eea;
            color: white;
        }

        .role-bmtadmin {
            background: #764ba2;
            color: white;
        }

        .role-admin {
            background: #28a745;
            color: white;
        }

        .content-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: #e9ecef;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            background: #e9ecef;
        }

        .user-badge .role-tag {
            font-size: 9px;
            background: #6c757d;
            color: white;
            padding: 1px 6px;
            border-radius: 8px;
        }

        .user-badge.admin-bg {
            background: #e8f0fe;
        }

        .user-badge.bmtadmin-bg {
            background: #f3e8ff;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 13px;
            }

            table th,
            table td {
                padding: 8px 10px;
            }

            .card {
                padding: 15px;
            }

            .modal {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>🛡️ Admin Dashboard</h1>
        <div class="header-buttons">
            <a href="logout.php" class="header-btn">🚪 Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success" id="successMsg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error" id="errorMsg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">👥 Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_content; ?></div>
                <div class="stat-label">📦 Total Content</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_content; ?></div>
                <div class="stat-label">✅ Active Content</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($content_stats); ?></div>
                <div class="stat-label">📊 Content Types</div>
            </div>
        </div>

        <!-- Content Stats -->
        <div class="card">
            <h2>📊 Content Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Content Type</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($content_stats as $stat): ?>
                        <tr>
                            <td><?php echo ucfirst(str_replace('local_', '', $stat['content_type'])); ?></td>
                            <td><?php echo $stat['count']; ?></td>
                            <td><?php echo $total_content > 0 ? round(($stat['count'] / $total_content) * 100, 1) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($content_stats)): ?>
                        <tr>
                            <td colspan="3" style="text-align:center; color:#999;">No content found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Users Management -->
        <div class="card">
            <h2>👥 User Management <span class="badge"><?php echo count($users); ?></span></h2>
            <div style="margin-bottom: 15px;">
                <button class="btn btn-success" onclick="openModal('addUserModal')">➕ Add New User</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="openResetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🔑 Reset PW</button>
                                <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🗑️ Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Activity Log -->
        <div class="card">
            <h2>📋 Recent Activity <span class="badge">Last 50 changes</span></h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td>#<?php echo $activity['id']; ?></td>
                            <td>
                                <span class="content-type-badge">
                                    <?php
                                    $icons = [
                                        'slideshow' => '🖼️',
                                        'youtube' => '▶️',
                                        'local_video' => '🎬',
                                        'local_audio' => '🎵',
                                        'message' => '💬',
                                        'website' => '🌐',
                                        'pdf' => '📑',
                                        'ppt' => '📊'
                                    ];
                                    $display_type = str_replace('local_', '', $activity['content_type']);
                                    echo isset($icons[$activity['content_type']]) ? $icons[$activity['content_type']] . ' ' : '📄 ';
                                    echo ucfirst($display_type);
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($activity['description']) ? htmlspecialchars(substr($activity['description'], 0, 50)) : 'No description'; ?>
                                <?php if (strlen($activity['description'] ?? '') > 50): ?>...<?php endif; ?>
                            </td>
                            <td><?php echo formatDuration($activity['display_duration']); ?></td>
                            <td>
                                <?php if ($activity['is_active']): ?>
                                    <span style="color:#28a745;">✅ Active</span>
                                <?php else: ?>
                                    <span style="color:#dc3545;">⛔ Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($activity['updated_at'])); ?></td>
                            <td>
                                <?php if (!empty($activity['username'])): ?>
                                    <span class="user-badge <?php echo $activity['role'] . '-bg'; ?>">
                                        👤 <?php echo htmlspecialchars($activity['username']); ?>
                                        <span class="role-tag"><?php echo $activity['role']; ?></span>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#999; font-size:12px;">System</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#999;">No activity found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Error Logs -->
        <div class="card">
            <h2>⚠️ Recent Errors & Bugs <span class="badge">Last 20 entries</span></h2>
            <?php if (!empty($error_logs)): ?>
                <div style="background: #1a1a2e; color: #ff6b6b; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">
                    <?php
                    $log_lines = array_reverse($error_logs);
                    foreach ($log_lines as $line) {
                        echo htmlspecialchars($line) . "\n";
                    }
                    ?>
                </div>
            <?php else: ?>
                <p style="color:#28a745;">✅ No errors found in logs</p>
            <?php endif; ?>
        </div>

        <!-- Reset Password Modal -->
        <div id="resetPasswordModal" class="modal-overlay">
            <div class="modal">
                <h3>🔑 Reset Password</h3>
                <form method="POST">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <p style="margin-bottom: 15px;">Resetting password for: <strong id="resetUsername"></strong></p>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="newPassword" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="confirmPassword" required minlength="6" oninput="checkPasswordMatch()">
                        <small id="passwordMatchMsg" style="color:#dc3545;"></small>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')" style="background:#6c757d;color:white;">Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="addUserModal" class="modal-overlay">
            <div class="modal">
                <h3>➕ Add New User</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="admin">LMT Admin</option>
                            <option value="bmtadmin">BMT Admin</option>
                            <option value="admin">System Admin</option>
                        </select>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')" style="background:#6c757d;color:white;">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-success">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide notifications
        setTimeout(function() {
            const successMsg = document.getElementById('successMsg');
            const errorMsg = document.getElementById('errorMsg');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.classList.add('fade-out');
                    setTimeout(() => {
                        if (successMsg) successMsg.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            if (errorMsg) {
                setTimeout(() => {
                    errorMsg.classList.add('fade-out');
                    setTimeout(() => {
                        if (errorMsg) errorMsg.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        }, 100);

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openResetPassword(userId, username) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            document.getElementById('passwordMatchMsg').textContent = '';
            openModal('resetPasswordModal');
        }

        function checkPasswordMatch() {
            const pass1 = document.getElementById('newPassword').value;
            const pass2 = document.getElementById('confirmPassword').value;
            const msg = document.getElementById('passwordMatchMsg');
            if (pass1 && pass2) {
                if (pass1 === pass2) {
                    msg.textContent = '✅ Passwords match';
                    msg.style.color = '#28a745';
                } else {
                    msg.textContent = '❌ Passwords do not match';
                    msg.style.color = '#dc3545';
                }
            } else {
                msg.textContent = '';
            }
        }

        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone!`)) {
                window.location.href = '?delete_user=' + userId;
            }
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>