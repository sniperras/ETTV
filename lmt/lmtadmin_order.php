<?php
require_once '../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('Location: ../admin/login.php');
    exit();
}

// Add display_order column if not exists (run once)
try {
    $pdo->exec("ALTER TABLE content ADD COLUMN IF NOT EXISTS display_order INT DEFAULT NULL");
} catch (PDOException $e) {
    // Column might already exist
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

            // Set the first item as active, others inactive
            if (!empty($order_data)) {
                $first_id = $order_data[0]['id'];
                $stmt = $pdo->prepare("UPDATE content SET is_active = 0 WHERE admin_role = 'lmt'");
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE content SET is_active = 1 WHERE id = ? AND admin_role = 'lmt'");
                $stmt->execute([$first_id]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Display order saved successfully!";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
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

        // Also delete associated slides
        $stmt2 = $pdo->prepare("DELETE FROM content_slides WHERE content_id = ?");
        $stmt2->execute([$delete_id]);

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

// Get all content for LMT (ordered by display_order, then id)
$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = 'lmt' ORDER BY COALESCE(display_order, 999999) ASC, id ASC");
$stmt->execute();
$contents = $stmt->fetchAll();

// Get preview data for each content
foreach ($contents as &$content) {
    if ($content['content_type'] === 'slideshow') {
        $stmt2 = $pdo->prepare("SELECT image_path FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC LIMIT 1");
        $stmt2->execute([$content['id']]);
        $first_slide = $stmt2->fetch();
        $content['preview_image'] = $first_slide ? $first_slide['image_path'] : null;

        $stmt3 = $pdo->prepare("SELECT COUNT(*) as count FROM content_slides WHERE content_id = ?");
        $stmt3->execute([$content['id']]);
        $slide_count = $stmt3->fetch();
        $content['slide_count'] = $slide_count['count'];
    } elseif ($content['content_type'] === 'youtube') {
        $video_id = extractYouTubeID($content['content_data']);
        $content['preview_image'] = "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
        $content['video_id'] = $video_id;
    }
}

function extractYouTubeID($url)
{
    $patterns = [
        '/(?:youtube\.com\/watch\?v=)([^&]+)/',
        '/(?:youtu\.be\/)([^?]+)/',
        '/(?:youtube\.com\/embed\/)([^?]+)/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return $url;
}

// Get flash messages
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: scale(1.02);
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
            cursor: grabbing;
        }

        .order-item.drag-over {
            border-color: #667eea;
            background: #f0f0ff;
        }

        .order-item.selected {
            border-color: #28a745;
            background: #e8f5e9;
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

        .drag-handle:active {
            cursor: grabbing;
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
        }

        .item-details {
            font-size: 12px;
            color: #666;
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
            position: relative;
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
            font-size: 14px;
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

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
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
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="container">
        <div class="order-panel">
            <div class="order-header">
                <h3>📋 Display Sequence (Drag to reorder)</h3>
                <button id="saveOrderBtn" class="btn btn-primary">💾 Save Order</button>
            </div>
            <div class="order-list" id="orderList">
                <?php if (empty($contents)): ?>
                    <div style="text-align: center; padding: 40px; color: #999;">
                        No content found. <a href="lmtadmin.php">Create your first display content</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($contents as $index => $content): ?>
                        <div class="order-item" data-id="<?php echo $content['id']; ?>" data-index="<?php echo $index; ?>">
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
                                        <?php echo ucfirst($content['content_type']); ?>
                                        <?php if ($content['content_type'] === 'slideshow' && isset($content['slide_count'])): ?>
                                            <span style="font-size: 11px; color: #666;">(<?php echo $content['slide_count']; ?> slides)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        Duration: <?php echo formatDuration($content['display_duration']); ?>
                                        <?php if ($content['is_active'] == 1): ?>
                                            <span style="color: #28a745;">✅ Active</span>
                                        <?php else: ?>
                                            <span style="color: #999;">⏸️ Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="item-actions">
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
                <p style="font-size: 12px; margin-top: 5px;">Click on any item to preview</p>
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
            <form method="POST" id="editForm">
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

        function initDragAndDrop() {
            const items = document.querySelectorAll('.order-item');
            items.forEach(item => {
                item.setAttribute('draggable', 'true');
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('dragenter', handleDragEnter);
                item.addEventListener('dragleave', handleDragLeave);
                item.addEventListener('drop', handleDrop);
                item.addEventListener('click', (e) => {
                    if (!e.target.closest('.item-actions')) {
                        selectItem(item);
                    }
                });
            });
        }

        function handleDragStart(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            document.querySelectorAll('.order-item').forEach(item => {
                item.classList.remove('drag-over');
            });
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }

        function handleDragEnter(e) {
            e.preventDefault();
            if (this !== draggedItem) {
                this.classList.add('drag-over');
            }
        }

        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            if (draggedItem && this !== draggedItem) {
                const parent = document.getElementById('orderList');
                const draggedIndex = Array.from(parent.children).indexOf(draggedItem);
                const targetIndex = Array.from(parent.children).indexOf(this);
                if (draggedIndex < targetIndex) {
                    parent.insertBefore(draggedItem, this.nextSibling);
                } else {
                    parent.insertBefore(draggedItem, this);
                }
            }
        }

        function selectItem(item) {
            document.querySelectorAll('.order-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            const contentId = item.getAttribute('data-id');
            loadPreview(contentId);
        }

        function loadPreview(contentId) {
            const previewDiv = document.getElementById('previewContent');
            previewDiv.innerHTML = '<div class="preview-placeholder"><div class="preview-placeholder-icon">⏳</div><div>Loading preview...</div></div>';

            // Use absolute path for AJAX request
            fetch(`/ettv/get_content.php?id=${contentId}&t=${Date.now()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.content) {
                        displayPreview(data.content, data.slides || []);
                    } else {
                        showPreviewError(data.error || 'Content not found');
                    }
                })
                .catch(error => {
                    console.error('Preview error:', error);
                    showPreviewError('Failed to load preview: ' + error.message);
                });
        }

        function displayPreview(content, slides) {
            const previewDiv = document.getElementById('previewContent');

            switch (content.content_type) {
                case 'slideshow':
                    if (slides && slides.length > 0) {
                        let imagePath = slides[0].image_path;
                        if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                            imagePath = '/ettv/' + imagePath;
                        }
                        previewDiv.innerHTML = `
                            <div style="text-align: center; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #000;">
                                <img src="${imagePath}" style="max-width: 80%; max-height: 70%; object-fit: contain; border-radius: 10px;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22 viewBox=%220 0 100 100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%23333%22/%3E%3Ctext x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                                <div style="color: white; margin-top: 20px; text-align: center;">
                                    <strong>📸 Slideshow Preview</strong><br>
                                    ${slides.length} slide(s) | Duration: ${formatDurationPreview(content.display_duration)}
                                </div>
                            </div>
                        `;
                    } else {
                        previewDiv.innerHTML = `<div class="preview-placeholder">No slides available</div>`;
                    }
                    break;

                case 'youtube':
                    const videoId = extractYouTubeId(content.content_data);
                    previewDiv.innerHTML = `
                        <div style="text-align: center; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #000;">
                            <img src="https://img.youtube.com/vi/${videoId}/mqdefault.jpg" style="max-width: 80%; border-radius: 10px;">
                            <div style="color: white; margin-top: 20px; text-align: center;">
                                <strong>▶️ YouTube Video</strong><br>
                                Duration: ${formatDurationPreview(content.display_duration)}
                            </div>
                        </div>
                    `;
                    break;

                case 'message':
                    const icons = {
                        warning: '⚠️',
                        caution: '⚡',
                        memo: '📝',
                        congratulation: '🎉'
                    };
                    const icon = icons[content.message_type] || '📝';
                    previewDiv.innerHTML = `
                        <div style="text-align: center; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div style="background: rgba(255,255,255,0.9); border-radius: 20px; padding: 30px; max-width: 80%; text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 20px;">${icon}</div>
                                <div style="font-size: 18px; font-weight: bold;">${escapeHtml(content.content_data)}</div>
                                <div style="margin-top: 15px; font-size: 12px; color: #666;">Duration: ${formatDurationPreview(content.display_duration)}</div>
                            </div>
                        </div>
                    `;
                    break;

                default:
                    previewDiv.innerHTML = `<div class="preview-placeholder">Preview not available for this content type</div>`;
            }
        }

        function formatDurationPreview(seconds) {
            if (seconds < 60) return seconds + ' seconds';
            if (seconds < 3600) return Math.round(seconds / 60) + ' minutes';
            if (seconds < 86400) return Math.round(seconds / 3600) + ' hours';
            return Math.round(seconds / 86400) + ' days';
        }

        function extractYouTubeId(url) {
            const patterns = [
                /(?:youtube\.com\/watch\?v=)([^&]+)/,
                /(?:youtu\.be\/)([^?]+)/,
                /(?:youtube\.com\/embed\/)([^?]+)/
            ];
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

        function showPreviewError(message) {
            document.getElementById('previewContent').innerHTML = `
                <div class="preview-placeholder">
                    <div class="preview-placeholder-icon">⚠️</div>
                    <div>${message || 'Preview not available'}</div>
                </div>
            `;
        }

        function saveOrder() {
            const items = document.querySelectorAll('.order-item');
            const orderData = [];
            items.forEach((item, index) => {
                orderData.push({
                    id: parseInt(item.getAttribute('data-id'))
                });
            });

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
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

        function editDuration(id, currentDuration) {
            document.getElementById('editContentId').value = id;
            const durationSelect = document.getElementById('editDuration');
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
            durationSelect.value = selectedValue;
            document.getElementById('editModal').classList.add('active');
        }

        function deleteContent(id) {
            if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
                window.location.href = `?delete_id=${id}`;
            }
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            initDragAndDrop();
            document.getElementById('saveOrderBtn').addEventListener('click', saveOrder);
            document.getElementById('editModal').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });
            const firstItem = document.querySelector('.order-item');
            if (firstItem) selectItem(firstItem);
        });
    </script>
</body>

</html>