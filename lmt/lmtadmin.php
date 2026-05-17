<?php
require_once '../config/db.php';
require_once '../includes/upload_handler.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('Location: ../admin/login.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $content_type = $_POST['content_type'];
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $display_duration = convertToSeconds($_POST['display_duration']);
        $loop_count = isset($_POST['loop_count']) ? (int)$_POST['loop_count'] : 1;
        $insert_position = !empty($_POST['insert_position']) ? $_POST['insert_position'] : null;
        $make_first = isset($_POST['make_first']) ? true : false;
        $layout_type = isset($_POST['layout_type']) ? $_POST['layout_type'] : 'slideshow';
        
        // Flag to determine if we should force index refresh
        $force_refresh = $make_first;

        // Validate content type
        $allowed_types = ['slideshow', 'youtube', 'youtube_download', 'message', 'ppt'];
        if (!in_array($content_type, $allowed_types)) {
            throw new Exception('Invalid content type');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Get current max display_order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), -1) as max_order FROM content WHERE admin_role = 'lmt'");
        $stmt->execute();
        $max_order = $stmt->fetch()['max_order'];

        $new_display_order = $max_order + 1;

        // If make_first is checked, shift all orders up by 1 and set this to 0
        if ($make_first) {
            $stmt = $pdo->prepare("UPDATE content SET display_order = display_order + 1 WHERE admin_role = 'lmt' ORDER BY display_order DESC");
            $stmt->execute();
            $new_display_order = 0;
        }
        // If insert_position is specified, shift orders after that position
        else if ($insert_position !== null && $insert_position !== '' && $insert_position !== 'last') {
            $insert_pos = (int)$insert_position;
            $stmt = $pdo->prepare("UPDATE content SET display_order = display_order + 1 WHERE admin_role = 'lmt' AND display_order >= ? ORDER BY display_order DESC");
            $stmt->execute([$insert_pos + 1]);
            $new_display_order = $insert_pos + 1;
        }
        // If 'last' is selected, just append at the end
        else if ($insert_position === 'last') {
            $new_display_order = $max_order + 1;
        }

        if ($content_type === 'slideshow') {
            // Handle slideshow with multiple images
            if (!isset($_FILES['images']) || empty($_FILES['images']['tmp_name'][0])) {
                throw new Exception('Please upload at least one image');
            }

            // Collect all images first
            $uploaded_images = [];
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && !empty($tmp_name)) {
                    $file = [
                        'name' => $_FILES['images']['name'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ];

                    $image_path = validateAndUploadFile($file, '../uploads/', ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                    $duration = isset($_POST["duration_$key"]) ? (int)$_POST["duration_$key"] : 10;
                    $uploaded_images[] = [
                        'path' => $image_path,
                        'duration' => $duration
                    ];
                }
            }

            if ($layout_type === 'slideshow') {
                // Traditional slideshow - store in content_slides table
                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, 1, ?)");
                $stmt->execute(['lmt', $content_type, $description, $display_duration, $loop_count, $new_display_order]);
                $content_id = $pdo->lastInsertId();

                // Insert each slide
                $slide_order = 0;
                foreach ($uploaded_images as $image) {
                    $stmt2 = $pdo->prepare("INSERT INTO content_slides (content_id, image_path, duration, slide_order) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$content_id, $image['path'], $image['duration'], $slide_order]);
                    $slide_order++;
                }
            } else {
                // Multi-image layout - store as JSON in content_data
                $content_data = json_encode([
                    'type' => $layout_type,
                    'images' => $uploaded_images
                ]);

                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
                $stmt->execute(['lmt', $content_type, $description, $content_data, $display_duration, $loop_count, $new_display_order]);
            }
        } elseif ($content_type === 'ppt') {
            // Handle PPT/PDF upload
            if (!isset($_FILES['ppt_file']) || $_FILES['ppt_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid PDF file');
            }

            $file = $_FILES['ppt_file'];
            $allowed_files = ['pdf'];
            $file_path = validateAndUploadFile($file, '../uploads/', $allowed_files);

            $content_data = json_encode(['file_path' => $file_path]);

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', $content_type, $description, $content_data, $display_duration, $loop_count, $new_display_order]);
        } elseif ($content_type === 'youtube') {
            $youtube_link = filter_var($_POST['youtube_link'], FILTER_VALIDATE_URL);
            if (!$youtube_link) {
                throw new Exception('Invalid YouTube URL');
            }

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', $content_type, $youtube_link, $display_duration, $loop_count, $new_display_order]);
        } elseif ($content_type === 'youtube_download') {
            // Handle downloaded YouTube video
            $youtube_file = isset($_POST['youtube_file']) ? $_POST['youtube_file'] : '';
            $youtube_original_url = isset($_POST['youtube_original_url']) ? $_POST['youtube_original_url'] : '';
            
            if (empty($youtube_file)) {
                throw new Exception('Please download the YouTube video first');
            }
            
            // Verify file exists
            $fullPath = __DIR__ . '/..' . $youtube_file;
            if (!file_exists($fullPath)) {
                throw new Exception('Video file not found. Please download again.');
            }
            
            // Store as local video content
            $content_data = json_encode([
                'type' => 'local_video',
                'file_path' => $youtube_file,
                'original_url' => $youtube_original_url
            ]);
            
            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', 'local_video', $description, $content_data, $display_duration, $loop_count, $new_display_order]);
        } elseif ($content_type === 'message') {
            $message_text = htmlspecialchars($_POST['message_text'], ENT_QUOTES, 'UTF-8');
            $message_type = $_POST['message_type'];

            $allowed_types = ['warning', 'caution', 'memo', 'congratulation'];
            if (!in_array($message_type, $allowed_types)) {
                $message_type = 'memo';
            }

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, message_type, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', $content_type, $message_text, $message_type, $display_duration, $loop_count, $new_display_order]);
        }

        // Rebuild the chain after insertion
        $stmt = $pdo->prepare("SELECT id, is_active FROM content WHERE admin_role = 'lmt' ORDER BY display_order ASC, id ASC");
        $stmt->execute();
        $all_content = $stmt->fetchAll();

        // Clear all next_content_id
        $stmt = $pdo->prepare("UPDATE content SET next_content_id = NULL WHERE admin_role = 'lmt'");
        $stmt->execute();

        // Build chain only for active content in order
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

        // Update version for real-time refresh
        $stmt3 = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
        $stmt3->execute();

        $pdo->commit();

        $_SESSION['flash_success'] = "Content published successfully!";
        
        // Conditional redirect based on force_refresh flag
        if ($force_refresh) {
            // Refresh the index page immediately by triggering a higher version bump
            $stmt_extra = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt_extra->execute();
            $_SESSION['flash_success'] .= " TV will refresh immediately to show new first content.";
        } else {
            $_SESSION['flash_success'] .= " The TV will continue normal playback with updated sequence.";
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Check for flash messages
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
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

    return $duration_map[$duration_str] ?? 600;
}

// Get all content ordered by display_order (same as order manager)
$stmt = $pdo->prepare("SELECT id, content_type, description, created_at, display_order 
                       FROM content 
                       WHERE admin_role = 'lmt' 
                       ORDER BY COALESCE(display_order, 999999) ASC, id ASC");
$stmt->execute();
$all_content = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMT Admin Panel - ET TV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .header h1 {
            font-size: 24px;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .required:after {
            content: " *";
            color: red;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        select:focus,
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .image-inputs,
        .ppt-inputs {
            display: none;
        }

        .image-inputs.active,
        .ppt-inputs.active {
            display: block;
        }

        .image-item {
            margin-bottom: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            position: relative;
        }

        .image-item input[type="file"] {
            margin-bottom: 10px;
        }

        .duration-input {
            margin-top: 10px;
        }

        .duration-input input {
            width: 150px;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: scale(1.02);
        }

        .btn-add-image {
            background: #28a745;
            margin-top: 10px;
        }

        .btn-remove-image {
            background: #dc3545;
            margin-top: 10px;
            margin-left: 10px;
            padding: 8px 15px;
        }

        .btn-download {
            background: #ff4444;
            white-space: nowrap;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .duration-select {
            width: auto;
            min-width: 200px;
        }

        .layout-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 8px;
            font-size: 12px;
            color: #666;
        }

        .description-hint {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }

        .description-field {
            display: none;
        }

        .description-field.active {
            display: block;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .checkbox-group input {
            width: auto;
            margin: 0;
            transform: scale(1.2);
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .checkbox-group small {
            font-size: 11px;
            color: #666;
            margin-left: auto;
        }

        .insert-position-group {
            margin-top: 10px;
            padding: 15px;
            background: #f0f7ff;
            border-radius: 10px;
            border: 1px solid #cce5ff;
        }

        .download-progress {
            display: none;
            margin-top: 10px;
        }

        .progress-bar-container {
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            width: 0%;
            height: 20px;
            background: #28a745;
            transition: width 0.3s;
        }

        .download-result {
            margin-top: 10px;
        }

        .download-success {
            color: green;
            padding: 10px;
            background: #d4edda;
            border-radius: 5px;
        }

        .download-error {
            color: red;
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .container {
                margin: 20px auto;
                padding: 10px;
            }

            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>🎬 LMT Admin Dashboard</h1>
        <div class="header-buttons">
            <a href="lmtadmin_order.php" class="header-btn">📋 Manage Display Order</a>
            <a href="../admin/logout.php" class="header-btn">🚪 Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2>➕ Create New Display Content</h2>
            <form method="POST" enctype="multipart/form-data" id="contentForm">
                <div class="form-group">
                    <label class="required">Content Type</label>
                    <select name="content_type" id="contentType" required>
                        <option value="">Select Content Type</option>
                        <option value="slideshow">🖼️ Images / Slideshow</option>
                        <option value="youtube">▶️ YouTube Video (Embed)</option>
                        <option value="youtube_download">📥 YouTube (Download & Play Locally)</option>
                        <option value="message">💬 Custom Message</option>
                        <option value="ppt">📄 PDF Document</option>
                    </select>
                </div>

                <!-- Description Field - Only for Images/Slideshow and PDF -->
                <div class="form-group description-field" id="descriptionField">
                    <label>📝 Description (Optional)</label>
                    <textarea name="description" id="description" placeholder="Enter a description for this content (e.g., 'Product Launch Images', 'Annual Report PDF', etc.)" maxlength="500"></textarea>
                    <div class="description-hint">This description will help you identify this content in the order manager</div>
                </div>

                <!-- Position Selection (Replaces Next Content dropdown) -->
                <div class="form-group">
                    <label>📍 Display Position</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="make_first" id="makeFirst" value="1">
                        <label for="makeFirst">⭐ Make this the FIRST display content</label>
                        <small>Automatically becomes #1 in sequence (TV will refresh immediately)</small>
                    </div>

                    <div id="insertPositionGroup" class="insert-position-group">
                        <label>📌 Insert after position:</label>
                        <select name="insert_position" id="insertPosition">
                            <option value="last">-- At the end (after all existing content) --</option>
                            <?php
                            $position = 1;
                            foreach ($all_content as $content):
                            ?>
                                <option value="<?php echo $content['display_order'] ?? ($position - 1); ?>">
                                    After #<?php echo $position; ?> -
                                    <?php
                                    $type_icon = '';
                                    if ($content['content_type'] === 'slideshow') $type_icon = '🖼️';
                                    elseif ($content['content_type'] === 'youtube') $type_icon = '▶️';
                                    elseif ($content['content_type'] === 'message') $type_icon = '💬';
                                    else $type_icon = '📄';
                                    echo $type_icon . ' ' . ucfirst($content['content_type']);
                                    if (!empty($content['description'])) {
                                        echo ' - ' . htmlspecialchars(substr($content['description'], 0, 30));
                                    }
                                    ?>
                                </option>
                            <?php
                                $position++;
                            endforeach;
                            ?>
                        </select>
                        <div class="info-text">New content will appear AFTER the selected position in the display sequence. TV continues normal playback.</div>
                    </div>
                </div>

                <div class="form-group" id="layoutTypeGroup" style="display: none;">
                    <label>Display Layout</label>
                    <select name="layout_type" id="layoutType">
                        <option value="slideshow">Slideshow (One image at a time)</option>
                        <option value="2-image">2 Images Side by Side</option>
                        <option value="3-image">3 Images Side by Side</option>
                        <option value="4-image">4 Images Grid (2x2)</option>
                    </select>
                    <div class="layout-preview" id="layoutPreview">
                        💡 Selected layout will display all images at once in the chosen arrangement
                    </div>
                </div>

                <div id="slideshowUpload" class="image-inputs">
                    <label>📸 Upload Images</label>
                    <div id="imagesContainer">
                        <div class="image-item" data-index="0">
                            <label>Image 1</label>
                            <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/bmp">
                            <div class="duration-input" id="duration_0_container">
                                <label>Display duration (seconds):</label>
                                <input type="number" name="duration_0" value="10" min="1" max="3600" step="1">
                            </div>
                            <button type="button" class="btn-remove-image" onclick="removeImage(this)" style="display:none;">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-image" onclick="addImage()">+ Add Another Image</button>
                    <div class="info-text">Supported formats: JPG, PNG, GIF, BMP (Max 10MB each)</div>
                </div>

                <div id="pptUpload" class="ppt-inputs">
                    <div class="form-group">
                        <label>📑 PDF Document</label>
                        <input type="file" name="ppt_file" accept=".pdf">
                        <div class="info-text">Upload PDF document. For PowerPoint files, convert to PDF first.</div>
                    </div>
                </div>

                <!-- Regular YouTube Embed -->
                <div id="youtubeLink" class="image-inputs">
                    <div class="form-group">
                        <label>🎬 YouTube URL</label>
                        <input type="url" name="youtube_link" placeholder="https://www.youtube.com/watch?v=...">
                        <div class="info-text">Video will play without controls. Duration is controlled by "Overall Display Duration" below.</div>
                    </div>
                </div>

                <!-- YouTube Download Section -->
                <div id="youtubeDownload" class="image-inputs" style="display: none;">
                    <div class="form-group">
                        <label>🎬 YouTube URL (Download & Play Locally)</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="url" id="youtube_download_url" placeholder="https://www.youtube.com/watch?v=..." style="flex: 1;">
                            <button type="button" id="downloadYoutubeBtn" class="btn btn-download">⬇️ Download & Save</button>
                        </div>
                        <div id="downloadProgress" class="download-progress">
                            <div class="progress-bar-container">
                                <div id="progressBar" class="progress-bar"></div>
                            </div>
                            <p id="progressText" style="font-size: 12px; margin-top: 5px;">Downloading video... This may take a minute.</p>
                        </div>
                        <div id="downloadResult" class="download-result"></div>
                        <div class="info-text">Download video to your server for reliable playback. Works without autoplay restrictions. Max 100MB for free hosting.</div>
                    </div>
                    
                    <input type="hidden" name="youtube_file" id="youtube_file">
                    <input type="hidden" name="youtube_original_url" id="youtube_original_url">
                </div>

                <div id="customMessage" class="image-inputs">
                    <div class="form-group">
                        <label>💬 Message Text</label>
                        <textarea name="message_text" placeholder="Enter your message here..." maxlength="500"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Message Type</label>
                        <select name="message_type">
                            <option value="memo">📝 Memo</option>
                            <option value="warning">⚠️ Warning</option>
                            <option value="caution">⚡ Caution</option>
                            <option value="congratulation">🎉 Congratulation</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>⏱️ Overall Display Duration</label>
                    <select name="display_duration" class="duration-select">
                        <option value="30s">30 seconds</option>
                        <option value="1m">1 minute</option>
                        <option value="5m" selected>5 minutes</option>
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
                    <div class="info-text">How long to show this content before moving to next content</div>
                </div>

                <div class="form-group" id="loopGroup" style="display: none;">
                    <label>🔄 Loop Count (for videos)</label>
                    <input type="number" name="loop_count" value="1" min="1" max="100">
                    <div class="info-text">How many times to loop the video</div>
                </div>

                <button type="submit">🚀 Publish to TV</button>
            </form>
        </div>
    </div>

    <script>
        let imageCount = 1;

        const contentType = document.getElementById('contentType');
        const slideshowUpload = document.getElementById('slideshowUpload');
        const pptUpload = document.getElementById('pptUpload');
        const youtubeLink = document.getElementById('youtubeLink');
        const youtubeDownload = document.getElementById('youtubeDownload');
        const customMessage = document.getElementById('customMessage');
        const loopGroup = document.getElementById('loopGroup');
        const layoutTypeGroup = document.getElementById('layoutTypeGroup');
        const layoutType = document.getElementById('layoutType');
        const descriptionField = document.getElementById('descriptionField');
        const makeFirst = document.getElementById('makeFirst');
        const insertPositionGroup = document.getElementById('insertPositionGroup');
        const downloadYoutubeBtn = document.getElementById('downloadYoutubeBtn');
        const youtubeDownloadUrl = document.getElementById('youtube_download_url');
        const downloadProgress = document.getElementById('downloadProgress');
        const downloadResult = document.getElementById('downloadResult');
        const progressBar = document.getElementById('progressBar');

        // Handle checkbox for "Make First"
        makeFirst.addEventListener('change', function() {
            if (this.checked) {
                insertPositionGroup.style.display = 'none';
                document.getElementById('insertPosition').value = '';
            } else {
                insertPositionGroup.style.display = 'block';
            }
        });

        function updateDescriptionVisibility() {
            const selectedType = contentType.value;
            if (selectedType === 'slideshow' || selectedType === 'ppt') {
                descriptionField.classList.add('active');
            } else {
                descriptionField.classList.remove('active');
                document.getElementById('description').value = '';
            }
        }

        function updateDurationVisibility() {
            const isSlideshow = layoutType.value === 'slideshow';
            const durationContainers = document.querySelectorAll('[id^="duration_"]');
            durationContainers.forEach(container => {
                if (container.id.endsWith('_container')) {
                    container.style.display = isSlideshow ? 'block' : 'none';
                }
            });

            const preview = document.getElementById('layoutPreview');
            if (layoutType.value === 'slideshow') {
                preview.innerHTML = '💡 Slideshow mode: Images will rotate one at a time with individual durations';
            } else if (layoutType.value === '2-image') {
                preview.innerHTML = '📸 2-Image Layout: Both images displayed side by side horizontally';
            } else if (layoutType.value === '3-image') {
                preview.innerHTML = '📸 3-Image Layout: All three images displayed side by side horizontally';
            } else if (layoutType.value === '4-image') {
                preview.innerHTML = '📸 4-Image Layout: Images displayed in a 2x2 grid';
            }
        }

        contentType.addEventListener('change', function() {
            slideshowUpload.classList.remove('active');
            pptUpload.classList.remove('active');
            youtubeLink.classList.remove('active');
            youtubeDownload.style.display = 'none';
            customMessage.classList.remove('active');

            updateDescriptionVisibility();

            if (this.value === 'slideshow') {
                slideshowUpload.classList.add('active');
                layoutTypeGroup.style.display = 'block';
                loopGroup.style.display = 'none';
                updateDurationVisibility();
            } else if (this.value === 'ppt') {
                pptUpload.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else if (this.value === 'youtube') {
                youtubeLink.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'block';
            } else if (this.value === 'youtube_download') {
                youtubeDownload.style.display = 'block';
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else if (this.value === 'message') {
                customMessage.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else {
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            }
        });

        layoutType.addEventListener('change', updateDurationVisibility);

        function addImage() {
            const container = document.getElementById('imagesContainer');
            const newIndex = imageCount;

            const imageDiv = document.createElement('div');
            imageDiv.className = 'image-item';
            imageDiv.setAttribute('data-index', newIndex);
            imageDiv.innerHTML = `
                <label>Image ${newIndex + 1}</label>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/bmp">
                <div class="duration-input" id="duration_${newIndex}_container">
                    <label>Display duration (seconds):</label>
                    <input type="number" name="duration_${newIndex}" value="10" min="1" max="3600" step="1">
                </div>
                <button type="button" class="btn-remove-image" onclick="removeImage(this)">Remove</button>
            `;

            container.appendChild(imageDiv);
            imageCount++;
            updateDurationVisibility();
        }

        function removeImage(button) {
            const imageItem = button.parentElement;
            imageItem.remove();
            const remainingImages = document.querySelectorAll('.image-item');
            remainingImages.forEach((img, idx) => {
                img.setAttribute('data-index', idx);
                const label = img.querySelector('label');
                if (label) label.textContent = `Image ${idx + 1}`;
                const durationInput = img.querySelector('.duration-input input');
                if (durationInput && durationInput.name) {
                    durationInput.name = `duration_${idx}`;
                }
                const durationContainer = img.querySelector('.duration-input');
                if (durationContainer) {
                    durationContainer.id = `duration_${idx}_container`;
                }
            });
            imageCount = remainingImages.length;
            updateDurationVisibility();
        }

        // YouTube download functionality
        if (downloadYoutubeBtn) {
            downloadYoutubeBtn.addEventListener('click', async function() {
                const url = youtubeDownloadUrl.value;
                if (!url) {
                    alert('Please enter a YouTube URL');
                    return;
                }
                
                downloadProgress.style.display = 'block';
                downloadResult.innerHTML = '';
                progressBar.style.width = '30%';
                this.disabled = true;
                this.textContent = 'Downloading...';
                
                const formData = new FormData();
                formData.append('url', url);
                
                try {
                    const response = await fetch('youtube_proxy.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    progressBar.style.width = '80%';
                    const data = await response.json();
                    
                    if (data.success) {
                        progressBar.style.width = '100%';
                        document.getElementById('youtube_file').value = data.file_url;
                        document.getElementById('youtube_original_url').value = url;
                        downloadResult.innerHTML = `<div class="download-success">✅ ${data.message}</div>`;
                    } else {
                        downloadResult.innerHTML = `<div class="download-error">❌ Error: ${data.error}</div>`;
                        if (data.details) {
                            downloadResult.innerHTML += `<pre style="font-size: 11px; margin-top: 5px; color: red;">${data.details}</pre>`;
                        }
                    }
                } catch (error) {
                    downloadResult.innerHTML = `<div class="download-error">❌ Download failed: ${error.message}</div>`;
                } finally {
                    setTimeout(() => {
                        downloadProgress.style.display = 'none';
                        this.disabled = false;
                        this.textContent = '⬇️ Download & Save';
                        progressBar.style.width = '0%';
                    }, 2000);
                }
            });
        }

        document.getElementById('contentForm').addEventListener('submit', function(e) {
            const type = contentType.value;
            if (!type) {
                alert('Please select a content type');
                e.preventDefault();
                return;
            }

            if (type === 'slideshow') {
                const fileInputs = document.querySelectorAll('#imagesContainer input[type="file"]');
                let hasFiles = false;
                fileInputs.forEach(input => {
                    if (input.files.length > 0) hasFiles = true;
                });
                if (!hasFiles) {
                    alert('Please upload at least one image.');
                    e.preventDefault();
                    return;
                }
            } else if (type === 'ppt') {
                const pptFile = document.querySelector('input[name="ppt_file"]');
                if (!pptFile || !pptFile.files.length) {
                    alert('Please upload a PDF file.');
                    e.preventDefault();
                }
            } else if (type === 'youtube') {
                const youtubeLink = document.querySelector('input[name="youtube_link"]');
                if (!youtubeLink || !youtubeLink.value.trim()) {
                    alert('Please enter a YouTube URL.');
                    e.preventDefault();
                }
            } else if (type === 'youtube_download') {
                const youtubeFile = document.getElementById('youtube_file').value;
                if (!youtubeFile) {
                    alert('Please download the YouTube video first.');
                    e.preventDefault();
                }
            } else if (type === 'message') {
                const messageText = document.querySelector('textarea[name="message_text"]');
                if (!messageText || !messageText.value.trim()) {
                    alert('Please enter a message.');
                    e.preventDefault();
                }
            }
        });

        // Initialize
        loopGroup.style.display = 'none';
        layoutTypeGroup.style.display = 'none';
        descriptionField.classList.remove('active');

        setTimeout(function() {
            const successMsg = document.querySelector('.success');
            const errorMsg = document.querySelector('.error');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.style.display = 'none';
                }, 5000);
            }
            if (errorMsg) {
                setTimeout(() => {
                    errorMsg.style.display = 'none';
                }, 5000);
            }
        }, 1000);
    </script>
</body>

</html>