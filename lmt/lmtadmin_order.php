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

            // Update display_order for each content
            foreach ($order_data as $index => $item) {
                $stmt = $pdo->prepare("UPDATE content SET display_order = ? WHERE id = ? AND admin_role = 'lmt'");
                $stmt->execute([$index, $item['id']]);
            }

            // Build the chain: set next_content_id based on order
            $prev_id = null;
            foreach ($order_data as $item) {
                if ($prev_id !== null) {
                    $stmt = $pdo->prepare("UPDATE content SET next_content_id = ? WHERE id = ?");
                    $stmt->execute([$item['id'], $prev_id]);
                }
                $prev_id = $item['id'];
            }
            // Last item has no next content
            if ($prev_id !== null) {
                $stmt = $pdo->prepare("UPDATE content SET next_content_id = NULL WHERE id = ?");
                $stmt->execute([$prev_id]);
            }

            // Set ALL content as active (they will play in sequence)
            $stmt = $pdo->prepare("UPDATE content SET is_active = 1 WHERE admin_role = 'lmt'");
            $stmt->execute();

            // Update version for real-time refresh
            $stmt = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt->execute();

            $pdo->commit();
            $_SESSION['flash_success'] = "Display order saved successfully! TV will play in this sequence.";
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
        $stmt = $pdo->prepare("UPDATE content SET is_active = NOT is_active WHERE id = ? AND admin_role = 'lmt'");
        $stmt->execute([$content_id]);

        $_SESSION['flash_success'] = "Content status updated!";
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

        $stmt3 = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
        $stmt3->execute();

        $_SESSION['flash_success'] = "Content deleted successfully!";
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

        $_SESSION['flash_success'] = "Duration updated successfully!";
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

// Get layout type from content_data for display
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

// Get all content for LMT ordered by display_order
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

        .btn-info {
            background: #17a2b8;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: bold;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin: 10px 20px 0 20px;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin: 10px 20px 0 20px;
            border-left: 4px solid #dc3545;
        }

        .fade-out {
            animation: fadeOut 3s ease forwards;
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            70% {
                opacity: 1;
            }

            100% {
                opacity: 0;
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

    <?php if ($success): ?>
        <div class="success" id="successMsg"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error" id="errorMsg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="container">
        <div class="order-panel">
            <div class="order-header">
                <h3>📋 Display Sequence (Drag to reorder)</h3>
                <button id="saveOrderBtn" class="btn btn-primary">💾 Save Order & Activate All</button>
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
                                    else echo '📄';
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
                                        <?php if ($content['content_type'] === 'slideshow' && $layout_type === 'slideshow'): ?>
                                            | 🎞️ Slideshow mode
                                        <?php endif; ?>
                                        <?php if ($content['is_active']): ?>
                                            <span style="color: #28a745; margin-left: 8px;">✅ Active</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545; margin-left: 8px;">⛔ Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="?toggle_active=<?php echo $content['id']; ?>" class="btn btn-<?php echo $content['is_active'] ? 'secondary' : 'success'; ?> btn-icon" onclick="return confirm('Toggle active status?')">
                                    <?php echo $content['is_active'] ? '🔴 Deactivate' : '🟢 Activate'; ?>
                                </a>
                                <button class="btn btn-warning btn-icon" onclick="editDuration(<?php echo $content['id']; ?>, <?php echo $content['display_duration']; ?>)">✏️ Edit</button>
                                <button class="btn btn-danger btn-icon" onclick="deleteContent(<?php echo $content['id']; ?>)">🗑️ Delete</button>
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

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Edit Display Duration</div>
            <form method="POST">
                <input type="hidden" name="content_id" id="editContentId">
                <div class="modal-body">
                    <label>Display Duration:</label>
                    <select name="display_duration" id="editDuration" required>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="edit_duration" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let draggedItem = null;

        setTimeout(function() {
            const successMsg = document.getElementById('successMsg');
            const errorMsg = document.getElementById('errorMsg');
            if (successMsg) successMsg.classList.add('fade-out');
            if (errorMsg) errorMsg.classList.add('fade-out');
        }, 2000);

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
            previewDiv.innerHTML = '<div class="preview-placeholder"><div class="preview-placeholder-icon">⏳</div><div>Loading preview...</div></div>';

            fetch('get_preview.php?id=' + contentId + '&t=' + Date.now())
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.content) displayPreview(data.content, data.slides || []);
                    else showPreviewError(data.error || 'Content not found');
                })
                .catch(() => showPreviewError('Failed to load preview'));
        }

        function displayPreview(content, slides) {
            const previewDiv = document.getElementById('previewContent');

            if (content.content_type === 'slideshow' && slides && slides.length > 0) {
                previewDiv.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#000;">
                        <img src="${slides[0].image_path}" style="max-width:90%;max-height:80%;object-fit:contain;border-radius:10px;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22 viewBox=%220 0 100 100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%23333%22/%3E%3Ctext x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22%3ENo Image%3C/text%3E%3C/svg%3E'">
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
                        <div style="background:rgba(255,255,255,0.9);border-radius:20px;padding:30px;text-align:center;">
                            <div style="font-size:64px;">${icons[content.message_type] || '📝'}</div>
                            <div style="margin-top:20px;font-weight:bold;">${escapeHtml(content.content_data)}</div>
                            <div style="margin-top:10px;font-size:12px;">Duration: ${formatDurationPreview(content.display_duration)}</div>
                        </div>
                    </div>`;
            } else if (content.content_type === 'ppt') {
                previewDiv.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#000;">
                        <div style="font-size:64px;">📄</div>
                        <div style="color:white;margin-top:20px;font-weight:bold;">PDF Document</div>
                        <div style="color:#aaa;">Duration: ${formatDurationPreview(content.display_duration)}</div>
                    </div>`;
            }
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

        function saveOrder() {
            if (confirm('Save this display order? ALL content will be activated and play in sequence.')) {
                const items = document.querySelectorAll('.order-item');
                const orderData = [];
                items.forEach((item, idx) => orderData.push({
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

        function deleteContent(id) {
            if (confirm('⚠️ Delete this content? Cannot be undone!')) window.location.href = `?delete_id=${id}`;
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            initDragAndDrop();
            document.getElementById('saveOrderBtn').addEventListener('click', saveOrder);
            document.getElementById('editModal').addEventListener('click', e => {
                if (e.target === e.currentTarget) closeModal();
            });
            const firstItem = document.querySelector('.order-item');
            if (firstItem) selectItem(firstItem);
        });
    </script>
</body>

</html>