<?php
require_once __DIR__ . '/../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('Location: ../admin/login.php');
    exit();
}

// Add display_order column if not exists
try {
    $pdo->exec("ALTER TABLE content ADD COLUMN IF NOT EXISTS display_order INT DEFAULT NULL");
} catch (PDOException $e) {
}

// Handle save order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    try {
        $order_data = json_decode($_POST['order_data'], true);

        if ($order_data && is_array($order_data)) {
            $pdo->beginTransaction();

            foreach ($order_data as $index => $item) {
                $stmt = $pdo->prepare("UPDATE content SET display_order = ? WHERE id = ? AND admin_role = 'lmt'");
                $stmt->execute([$index, $item['id']]);
            }

            $stmt = $pdo->prepare("UPDATE content SET next_content_id = NULL WHERE admin_role = 'lmt'");
            $stmt->execute();

            $active_items = [];
            foreach ($order_data as $item) {
                $stmt = $pdo->prepare("SELECT is_active FROM content WHERE id = ?");
                $stmt->execute([$item['id']]);
                $content = $stmt->fetch();
                if ($content && $content['is_active'] == 1) {
                    $active_items[] = $item['id'];
                }
            }

            for ($i = 0; $i < count($active_items) - 1; $i++) {
                $stmt = $pdo->prepare("UPDATE content SET next_content_id = ? WHERE id = ?");
                $stmt->execute([$active_items[$i + 1], $active_items[$i]]);
            }

            $stmt = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt->execute();

            $pdo->commit();
            $_SESSION['flash_success'] = "Display order saved successfully! TV will update immediately.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    header('Location: lmtadmin_order.php');
    exit();
}

// Handle toggle active status
if (isset($_GET['toggle_active'])) {
    try {
        $content_id = (int)$_GET['toggle_active'];

        $stmt = $pdo->prepare("SELECT is_active FROM content WHERE id = ? AND admin_role = 'lmt'");
        $stmt->execute([$content_id]);
        $current = $stmt->fetch();

        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE content SET is_active = ? WHERE id = ? AND admin_role = 'lmt'");
            $stmt->execute([$new_status, $content_id]);

            $stmt = $pdo->prepare("SELECT id, is_active FROM content WHERE admin_role = 'lmt' ORDER BY COALESCE(display_order, 999999) ASC, id ASC");
            $stmt->execute();
            $all_content = $stmt->fetchAll();

            $stmt = $pdo->prepare("UPDATE content SET next_content_id = NULL WHERE admin_role = 'lmt'");
            $stmt->execute();

            $active_ids = [];
            foreach ($all_content as $item) {
                if ($item['is_active'] == 1) {
                    $active_ids[] = $item['id'];
                }
            }

            for ($i = 0; $i < count($active_ids) - 1; $i++) {
                $stmt = $pdo->prepare("UPDATE content SET next_content_id = ? WHERE id = ?");
                $stmt->execute([$active_ids[$i + 1], $active_ids[$i]]);
            }

            $stmt = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt->execute();
        }

        $_SESSION['flash_success'] = "Content status updated! TV will update immediately.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    header('Location: lmtadmin_order.php');
    exit();
}

// Handle delete content
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = (int)$_GET['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM content WHERE id = ? AND admin_role = 'lmt'");
        $stmt->execute([$delete_id]);

        $stmt2 = $pdo->prepare("DELETE FROM content_slides WHERE content_id = ?");
        $stmt2->execute([$delete_id]);

        $stmt = $pdo->prepare("SELECT id, is_active FROM content WHERE admin_role = 'lmt' ORDER BY COALESCE(display_order, 999999) ASC, id ASC");
        $stmt->execute();
        $all_content = $stmt->fetchAll();

        $stmt = $pdo->prepare("UPDATE content SET next_content_id = NULL WHERE admin_role = 'lmt'");
        $stmt->execute();

        $active_ids = [];
        foreach ($all_content as $item) {
            if ($item['is_active'] == 1) {
                $active_ids[] = $item['id'];
            }
        }

        for ($i = 0; $i < count($active_ids) - 1; $i++) {
            $stmt = $pdo->prepare("UPDATE content SET next_content_id = ? WHERE id = ?");
            $stmt->execute([$active_ids[$i + 1], $active_ids[$i]]);
        }

        $stmt3 = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
        $stmt3->execute();

        $_SESSION['flash_success'] = "Content deleted successfully! TV will update immediately.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error deleting content: " . $e->getMessage();
    }
    header('Location: lmtadmin_order.php');
    exit();
}

// Handle edit duration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_duration'])) {
    try {
        $content_id = (int)$_POST['content_id'];
        $new_duration = convertToSeconds($_POST['display_duration']);

        $stmt = $pdo->prepare("UPDATE content SET display_duration = ? WHERE id = ? AND admin_role = 'lmt'");
        $stmt->execute([$new_duration, $content_id]);

        $stmt2 = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
        $stmt2->execute();

        $_SESSION['flash_success'] = "Duration updated successfully! TV will update immediately.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    header('Location: lmtadmin_order.php');
    exit();
}

function convertToSeconds($duration_str)
{
    $duration_map = [
        '30s' => 30,
        '1m' => 60,
        '5m' => 300,
        '10m' => 600,
        '15m' => 900,
        '30m' => 1800,
        '1h' => 3600,
        '2h' => 7200,
        '4h' => 14400,
        '8h' => 28800,
        '12h' => 43200,
        '24h' => 86400
    ];
    return $duration_map[$duration_str] ?? 300;
}

function formatDuration($seconds)
{
    if ($seconds < 60) return $seconds . ' seconds';
    if ($seconds < 3600) return round($seconds / 60) . ' minutes';
    if ($seconds < 86400) return round($seconds / 3600) . ' hours';
    return round($seconds / 86400) . ' days';
}

function getLayoutType($content_type, $content_data)
{
    if ($content_type !== 'slideshow') return null;
    $data = json_decode($content_data, true);
    if ($data && isset($data['type'])) {
        return $data['type'];
    }
    return 'slideshow';
}

function getLayoutIcon($layout_type)
{
    switch ($layout_type) {
        case '2-image':
            return '📸 2 Images';
        case '3-image':
            return '📸 3 Images';
        case '4-image':
            return '🎚️ 4 Grid';
        default:
            return '🎞️ Slideshow';
    }
}

$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = 'lmt' ORDER BY COALESCE(display_order, 999999) ASC, id ASC");
$stmt->execute();
$contents = $stmt->fetchAll();

$success = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMT Display Order Manager - ET TV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
        }

        .container {
            display: flex;
            height: calc(100vh - 80px);
            padding: 20px;
            gap: 20px;
        }

        .order-panel {
            flex: 1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .order-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .order-item {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-item.dragging {
            opacity: 0.5;
        }

        .order-item.drag-over {
            border-color: #667eea;
            background: #f0f0ff;
        }

        .order-item.selected {
            border-color: #28a745;
            background: #e8f5e9;
        }

        .order-item.inactive {
            opacity: 0.6;
            background: #e9ecef;
        }

        .order-item-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .drag-handle {
            font-size: 24px;
            cursor: grab;
            color: #999;
        }

        .item-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .item-info {
            flex: 1;
        }

        .item-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .item-description {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
            font-style: italic;
        }

        .item-details {
            font-size: 11px;
            color: #888;
            margin-top: 3px;
        }

        .item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 8px 12px;
            font-size: 12px;
        }

        .preview-panel {
            flex: 1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .preview-header {
            padding: 20px;
            background: #343a40;
            color: white;
        }

        .preview-content {
            flex: 1;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .preview-placeholder {
            text-align: center;
            color: #666;
        }

        .preview-placeholder-icon {
            font-size: 64px;
            margin-bottom: 20px;
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

        .confirmation-modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }

        .modal-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .modal-btn:hover {
            opacity: 0.9;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-confirm {
            background: #dc3545;
            color: white;
        }

        .modal-btn-confirm.success {
            background: #28a745;
        }

        .notification-container {
            position: fixed;
            top: 90px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2000;
            width: auto;
            min-width: 300px;
            max-width: 500px;
            pointer-events: none;
        }

        .success,
        .error {
            padding: 12px 20px;
            border-radius: 8px;
            margin: 0;
            border-left: 4px solid;
            text-align: center;
            font-weight: 500;
            pointer-events: auto;
            animation: slideDown 0.3s ease-out;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-out {
            animation: fadeOut 0.5s ease forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-20px);
                visibility: hidden;
            }
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 4px;
            background: #e9ecef;
            color: #495057;
            margin-left: 8px;
        }

        .pdf-preview-container {
            position: relative;
            width: 100%;
            height: 100%;
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
        }

        .pdf-toolbar {
            padding: 12px;
            background: #16213e;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .pdf-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .pdf-nav-btn {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 4px 12px;
            cursor: pointer;
            font-size: 12px;
        }

        .pdf-nav-btn:hover {
            opacity: 0.9;
        }

        .pdf-nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pdf-canvas-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 10px;
        }

        .pdf-canvas {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .pdf-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #1a1a2e;
            color: white;
            gap: 12px;
            z-index: 10;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🎬 LMT Display Order Manager</h1>
        <div class="header-buttons">
            <a href="lmtadmin.php" class="btn btn-primary">➕ Create New Content</a>
            <a href="../admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="notification-container" id="notificationContainer">
        <?php if ($success): ?>
            <div class="success" id="successMsg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error" id="errorMsg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    </div>

    <div id="confirmModal" class="modal-overlay">
        <div class="confirmation-modal">
            <div class="modal-icon" id="modalIcon">⚠️</div>
            <div class="modal-title" id="modalTitle">Confirm Action</div>
            <div class="modal-message" id="modalMessage">Are you sure?</div>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="modal-btn modal-btn-confirm" id="confirmBtn">Confirm</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="order-panel">
            <div class="order-header">
                <h3>📋 Display Sequence (Drag to reorder)</h3>
                <button id="saveOrderBtn" class="btn btn-primary">💾 Save Order</button>
            </div>
            <div class="order-list" id="orderList">
                <?php if (empty($contents)): ?>
                    <div style="text-align: center; padding: 40px;">No content found. <a href="lmtadmin.php">Create your first display content</a></div>
                <?php else: ?>
                    <?php foreach ($contents as $index => $content):
                        $layout_type = getLayoutType($content['content_type'], $content['content_data']);
                        $layout_icon = getLayoutIcon($layout_type);
                    ?>
                        <div class="order-item <?php echo $content['is_active'] ? '' : 'inactive'; ?>" data-id="<?php echo $content['id']; ?>">
                            <div class="order-item-content">
                                <div class="drag-handle">☰</div>
                                <div class="item-icon">
                                    <?php
                                    if ($content['content_type'] === 'slideshow') echo '🖼️';
                                    elseif ($content['content_type'] === 'youtube') echo '▶️';
                                    elseif ($content['content_type'] === 'message') echo '💬';
                                    else echo '📕';
                                    ?>
                                </div>
                                <div class="item-info">
                                    <div class="item-title">
                                        #<?php echo $index + 1; ?> - <?php echo ucfirst($content['content_type']); ?>
                                        <?php if ($content['content_type'] === 'slideshow' && $layout_type !== 'slideshow'): ?>
                                            <span class="badge"><?php echo $layout_icon; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($content['description']) && ($content['content_type'] === 'slideshow' || $content['content_type'] === 'ppt')): ?>
                                        <div class="item-description">
                                            📝 <?php echo htmlspecialchars(substr($content['description'], 0, 80)); ?>
                                            <?php if (strlen($content['description']) > 80): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-details">
                                        ⏱️ Duration: <?php echo formatDuration($content['display_duration']); ?>
                                        <?php if ($content['is_active']): ?>
                                            <span style="color: #28a745; margin-left: 8px;">✅ Active</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545; margin-left: 8px;">⛔ Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-<?php echo $content['is_active'] ? 'secondary' : 'success'; ?> btn-icon" onclick="showToggleModal(<?php echo $content['id']; ?>, <?php echo $content['is_active'] ? 'false' : 'true'; ?>)">
                                    <?php echo $content['is_active'] ? '🔴 Deactivate' : '🟢 Activate'; ?>
                                </button>
                                <button class="btn btn-warning btn-icon" onclick="editDuration(<?php echo $content['id']; ?>, <?php echo $content['display_duration']; ?>)">✏️ Edit</button>
                                <button class="btn btn-danger btn-icon" onclick="showDeleteModal(<?php echo $content['id']; ?>)">🗑️ Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="preview-panel">
            <div class="preview-header">
                <h3>👁️ Preview</h3>
                <p style="font-size: 12px;">Click on any item to preview</p>
            </div>
            <div class="preview-content" id="previewContent">
                <div class="preview-placeholder">
                    <div class="preview-placeholder-icon">🎯</div>
                    <div>Click on a display item to preview</div>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="confirmation-modal">
            <div class="modal-icon">✏️</div>
            <div class="modal-title">Edit Display Duration</div>
            <form method="POST">
                <input type="hidden" name="content_id" id="editContentId">
                <div style="margin-bottom: 20px;">
                    <select name="display_duration" id="editDuration" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <option value="30s">30 seconds</option>
                        <option value="1m">1 minute</option>
                        <option value="5m">5 minutes</option>
                        <option value="10m">10 minutes</option>
                        <option value="15m">15 minutes</option>
                        <option value="30m">30 minutes</option>
                        <option value="1h">1 hour</option>
                        <option value="2h">2 hours</option>
                        <option value="4h">4 hours</option>
                        <option value="8h">8 hours</option>
                        <option value="12h">12 hours</option>
                        <option value="24h">24 hours</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_duration" class="modal-btn modal-btn-confirm success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let draggedItem = null;
        let pdfDoc = null;
        let currentPdfPage = 1;
        let pdfRenderToken = 0;
        let currentPreviewInterval = null;

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
                }, 3000);
            }
            if (errorMsg) {
                setTimeout(() => {
                    errorMsg.classList.add('fade-out');
                    setTimeout(() => {
                        if (errorMsg) errorMsg.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        }, 100);

        function showConfirmModal(title, message, icon, onConfirm) {
            document.getElementById('modalIcon').innerHTML = icon;
            document.getElementById('modalTitle').innerHTML = title;
            document.getElementById('modalMessage').innerHTML = message;
            const confirmBtn = document.getElementById('confirmBtn');
            const newBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
            newBtn.onclick = () => {
                closeConfirmModal();
                onConfirm();
            };
            document.getElementById('confirmModal').classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function showToggleModal(id, activate) {
            const message = activate ? 'Are you sure you want to ACTIVATE this content? It will appear in the TV display sequence.' : 'Are you sure you want to DEACTIVATE this content? It will be hidden from the TV display.';
            showConfirmModal(activate ? '🟢 Activate Content' : '🔴 Deactivate Content', message, activate ? '✅' : '⚠️', () => {
                window.location.href = `?toggle_active=${id}`;
            });
        }

        function showDeleteModal(id) {
            showConfirmModal('🗑️ Delete Content', '⚠️ This action CANNOT be undone! The content and all its slides/images will be permanently removed.', '⚠️', () => {
                window.location.href = `?delete_id=${id}`;
            });
        }

        function initDragAndDrop() {
            const items = document.querySelectorAll('.order-item');
            items.forEach(item => {
                item.setAttribute('draggable', 'true');
                item.addEventListener('dragstart', e => {
                    draggedItem = item;
                    item.classList.add('dragging');
                });
                item.addEventListener('dragend', e => {
                    item.classList.remove('dragging');
                    document.querySelectorAll('.order-item').forEach(i => i.classList.remove('drag-over'));
                });
                item.addEventListener('dragover', e => e.preventDefault());
                item.addEventListener('dragenter', e => {
                    if (item !== draggedItem) item.classList.add('drag-over');
                });
                item.addEventListener('dragleave', e => item.classList.remove('drag-over'));
                item.addEventListener('drop', e => {
                    e.preventDefault();
                    item.classList.remove('drag-over');
                    if (draggedItem && item !== draggedItem) {
                        const parent = document.getElementById('orderList');
                        const draggedIndex = Array.from(parent.children).indexOf(draggedItem);
                        const targetIndex = Array.from(parent.children).indexOf(item);
                        if (draggedIndex < targetIndex) parent.insertBefore(draggedItem, item.nextSibling);
                        else parent.insertBefore(draggedItem, item);
                    }
                });
                item.addEventListener('click', (e) => {
                    if (!e.target.closest('.item-actions')) selectItem(item);
                });
            });
        }

        function selectItem(item) {
            document.querySelectorAll('.order-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            loadPreview(item.getAttribute('data-id'));
        }

        function loadPreview(contentId) {
            const previewDiv = document.getElementById('previewContent');
            if (currentPreviewInterval) clearInterval(currentPreviewInterval);
            previewDiv.innerHTML = '<div class="preview-placeholder"><div class="preview-placeholder-icon">⏳</div><div>Loading preview...</div></div>';

            fetch('get_preview.php?id=' + contentId + '&t=' + Date.now())
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.content) displayPreview(data.content, data.slides || []);
                    else showPreviewError(data.error || 'Content not found');
                })
                .catch(err => {
                    console.error('Preview error:', err);
                    showPreviewError('Failed to load preview');
                });
        }

        function displayPreview(content, slides) {
            const previewDiv = document.getElementById('previewContent');
            if (currentPreviewInterval) clearInterval(currentPreviewInterval);

            if (content.content_type === 'slideshow' && slides && slides.length > 0) {
                // Build full image URL for InfinityFree
                let imagePath = '/uploads/' + slides[0].image_path.split('/').pop();
                previewDiv.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#000;">
                        <img src="${imagePath}" style="max-width:90%;max-height:75%;object-fit:contain;border-radius:10px;" 
                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%23333%22/%3E%3Ctext x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                        <div style="color:white;margin-top:20px;">📸 Slideshow Preview<br>${slides.length} slide(s) | ${formatDurationPreview(content.display_duration)}</div>
                    </div>`;
            } else if (content.content_type === 'youtube') {
                const videoId = extractYouTubeId(content.content_data);
                previewDiv.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#000;">
                        <img src="https://img.youtube.com/vi/${videoId}/mqdefault.jpg" style="max-width:90%;border-radius:10px;">
                        <div style="color:white;margin-top:20px;">▶️ YouTube Video<br>Duration: ${formatDurationPreview(content.display_duration)}</div>
                    </div>`;
            } else if (content.content_type === 'message') {
                const icons = {
                    warning: '⚠️',
                    caution: '⚡',
                    memo: '📝',
                    congratulation: '🎉'
                };
                previewDiv.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;justify-content:center;align-items:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                        <div style="background:rgba(255,255,255,0.9);border-radius:20px;padding:30px;text-align:center;max-width:80%;">
                            <div style="font-size:64px;">${icons[content.message_type] || '📝'}</div>
                            <div style="margin-top:20px;font-weight:bold;">${escapeHtml(content.content_data)}</div>
                            <div style="margin-top:10px;font-size:12px;">Duration: ${formatDurationPreview(content.display_duration)}</div>
                        </div>
                    </div>`;
            } else if (content.content_type === 'ppt') {
                // Use proxy URL for PDF
                const pdfUrl = content.pdf_proxy_url || '/lmt/pdf_proxy.php?id=' + content.id;

                previewDiv.innerHTML = `
                    <div class="pdf-preview-container">
                        <div class="pdf-toolbar">
                            <span>📄 PDF Document</span>
                            <div class="pdf-nav-buttons">
                                <button class="pdf-nav-btn" id="pdfPrevBtn" disabled>◀ Prev</button>
                                <span id="pdfPageInfo">Loading...</span>
                                <button class="pdf-nav-btn" id="pdfNextBtn" disabled>Next ▶</button>
                            </div>
                            <span>${formatDurationPreview(content.display_duration)}</span>
                        </div>
                        <div class="pdf-canvas-container">
                            <canvas id="pdfCanvas" class="pdf-canvas"></canvas>
                        </div>
                        <div id="pdfLoading" class="pdf-loading">
                            <div style="font-size:40px;">📄</div>
                            <div>Loading PDF...</div>
                        </div>
                    </div>`;

                if (typeof pdfjsLib === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                    script.onload = () => {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        renderPdfPreview(pdfUrl);
                    };
                    document.head.appendChild(script);
                } else {
                    renderPdfPreview(pdfUrl);
                }
            } else {
                previewDiv.innerHTML = `<div class="preview-placeholder"><div class="preview-placeholder-icon">📄</div><div>Preview not available</div></div>`;
            }
        }

        function renderPdfPreview(pdfUrl) {
            pdfDoc = null;
            currentPdfPage = 1;
            const token = ++pdfRenderToken;

            const loading = document.getElementById('pdfLoading');
            if (loading) loading.style.display = 'flex';

            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                if (token !== pdfRenderToken) return;
                pdfDoc = pdf;
                const info = document.getElementById('pdfPageInfo');
                if (info) info.textContent = `Page 1 / ${pdf.numPages}`;

                const prevBtn = document.getElementById('pdfPrevBtn');
                const nextBtn = document.getElementById('pdfNextBtn');
                if (prevBtn) prevBtn.disabled = false;
                if (nextBtn) nextBtn.disabled = false;

                const loadingDiv = document.getElementById('pdfLoading');
                if (loadingDiv) loadingDiv.style.display = 'none';
                renderPdfPage(1);
            }).catch(err => {
                if (token !== pdfRenderToken) return;
                const loadingDiv = document.getElementById('pdfLoading');
                if (loadingDiv) loadingDiv.innerHTML = `<div style="font-size:40px;">⚠️</div><div>Failed to load PDF</div><div style="font-size:11px;">${err.message}</div>`;
            });
        }

        function renderPdfPage(pageNum) {
            if (!pdfDoc) return;
            pdfDoc.getPage(pageNum).then(page => {
                const canvas = document.getElementById('pdfCanvas');
                if (!canvas) return;
                const container = canvas.parentElement;
                const maxW = (container ? container.clientWidth : 600) - 20;
                const maxH = (container ? container.clientHeight : 400) - 20;
                const viewport = page.getViewport({
                    scale: 1
                });
                const scale = Math.min(maxW / viewport.width, maxH / viewport.height, 2);
                const scaledViewport = page.getViewport({
                    scale: Math.max(scale, 0.5)
                });
                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;
                page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: scaledViewport
                });

                const info = document.getElementById('pdfPageInfo');
                if (info) info.textContent = `Page ${pageNum} / ${pdfDoc.numPages}`;
                currentPdfPage = pageNum;
            });
        }

        function pdfPrevPage() {
            if (pdfDoc && currentPdfPage > 1) renderPdfPage(currentPdfPage - 1);
        }

        function pdfNextPage() {
            if (pdfDoc && currentPdfPage < pdfDoc.numPages) renderPdfPage(currentPdfPage + 1);
        }

        function formatDurationPreview(seconds) {
            if (seconds < 60) return seconds + ' seconds';
            if (seconds < 3600) return Math.round(seconds / 60) + ' minutes';
            return Math.round(seconds / 3600) + ' hours';
        }

        function extractYouTubeId(url) {
            const patterns = [/(?:youtube\.com\/watch\?v=)([^&]+)/, /(?:youtu\.be\/)([^?]+)/, /(?:youtube\.com\/embed\/)([^?]+)/];
            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) return match[1];
            }
            return url;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showPreviewError(msg) {
            document.getElementById('previewContent').innerHTML = `<div class="preview-placeholder"><div class="preview-placeholder-icon">⚠️</div><div>${msg || 'Preview not available'}</div></div>`;
        }

        function submitOrder() {
            const items = document.querySelectorAll('.order-item');
            const orderData = Array.from(items).map(item => ({
                id: parseInt(item.getAttribute('data-id'))
            }));
            const form = document.createElement('form');
            form.method = 'POST';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order_data';
            input.value = JSON.stringify(orderData);
            form.appendChild(input);
            const saveInput = document.createElement('input');
            saveInput.type = 'hidden';
            saveInput.name = 'save_order';
            saveInput.value = '1';
            form.appendChild(saveInput);
            document.body.appendChild(form);
            form.submit();
        }

        function saveOrder() {
            showConfirmModal('💾 Save Display Order', 'Save this display order? Active content will play in this sequence. The TV will update immediately.', '📋', submitOrder);
        }

        function editDuration(id, currentDuration) {
            document.getElementById('editContentId').value = id;
            let selectedValue = '5m';
            if (currentDuration <= 30) selectedValue = '30s';
            else if (currentDuration <= 60) selectedValue = '1m';
            else if (currentDuration <= 300) selectedValue = '5m';
            else if (currentDuration <= 600) selectedValue = '10m';
            else if (currentDuration <= 900) selectedValue = '15m';
            else if (currentDuration <= 1800) selectedValue = '30m';
            else if (currentDuration <= 3600) selectedValue = '1h';
            else if (currentDuration <= 7200) selectedValue = '2h';
            else if (currentDuration <= 14400) selectedValue = '4h';
            else if (currentDuration <= 28800) selectedValue = '8h';
            else if (currentDuration <= 43200) selectedValue = '12h';
            else selectedValue = '24h';
            document.getElementById('editDuration').value = selectedValue;
            document.getElementById('editModal').classList.add('active');
        }

        // Event delegation for PDF navigation buttons
        document.addEventListener('click', function(e) {
            if (e.target.id === 'pdfPrevBtn') pdfPrevPage();
            if (e.target.id === 'pdfNextBtn') pdfNextPage();
        });

        document.addEventListener('DOMContentLoaded', () => {
            initDragAndDrop();
            document.getElementById('saveOrderBtn').addEventListener('click', saveOrder);
            document.getElementById('editModal').addEventListener('click', e => {
                if (e.target === e.currentTarget) closeEditModal();
            });
            const firstItem = document.querySelector('.order-item');
            if (firstItem) selectItem(firstItem);
        });
    </script>
</body>

</html>