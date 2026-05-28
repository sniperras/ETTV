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

        // Handle custom duration or preset duration
        if (isset($_POST['display_duration_custom']) && !empty($_POST['display_duration_custom'])) {
            $display_duration = (int)$_POST['display_duration_custom'];
        } else {
            $display_duration = convertToSeconds($_POST['display_duration']);
        }

        $loop_count = isset($_POST['loop_count']) ? (int)$_POST['loop_count'] : 1;
        $insert_position = !empty($_POST['insert_position']) ? $_POST['insert_position'] : null;
        $make_first = isset($_POST['make_first']) ? true : false;
        $layout_type = isset($_POST['layout_type']) ? $_POST['layout_type'] : 'slideshow';

        // Validate content type
        $allowed_types = ['slideshow', 'youtube', 'video_upload', 'message', 'pdf', 'audio_upload', 'website'];
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
            $stmt = $pdo->prepare("UPDATE content SET display_order = display_order + 1 WHERE admin_role = 'lmt'");
            $stmt->execute();
            $new_display_order = 0;
        }
        // If insert_position is specified and not 'last', insert at that position
        else if ($insert_position !== null && $insert_position !== '' && $insert_position !== 'last') {
            $insert_pos = (int)$insert_position;
            // Shift items down from the insert position
            $stmt = $pdo->prepare("UPDATE content SET display_order = display_order + 1 WHERE admin_role = 'lmt' AND display_order >= ?");
            $stmt->execute([$insert_pos]);
            $new_display_order = $insert_pos;
        }
        // If 'last' is selected, just append at the end
        else if ($insert_position === 'last') {
            $new_display_order = $max_order + 1;
        }

        if ($content_type === 'slideshow') {
            // Handle slideshow with multiple images
            // Check all file inputs for files
            $has_files = false;
            foreach ($_FILES['images']['tmp_name'] as $tmp_name) {
                if (!empty($tmp_name)) {
                    $has_files = true;
                    break;
                }
            }

            if (!$has_files) {
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
        } elseif ($content_type === 'pdf') {
            // Handle PDF document upload
            if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid PDF file');
            }

            $file = $_FILES['pdf_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file_ext !== 'pdf') {
                throw new Exception('File must be PDF format. Detected: ' . $file_ext);
            }

            // Check file size
            $fileSize = $file['size'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($fileSize > $maxSize) {
                throw new Exception('File too large. Max 10MB allowed. Your file: ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            }

            // Create PDF directory if it doesn't exist
            $pdf_dir = '../uploads/pdf/';
            if (!file_exists($pdf_dir)) {
                mkdir($pdf_dir, 0777, true);
            }

            $file_path = validateAndUploadFile($file, $pdf_dir, ['pdf']);

            // Ensure the path is correct (remove any double slashes)
            $file_path = str_replace('//', '/', $file_path);

            // Store the correct path
            $content_data = json_encode([
                'type' => 'pdf',
                'file_path' => $file_path
            ]);

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', 'pdf', $description, $content_data, $display_duration, 1, $new_display_order]);
        } elseif ($content_type === 'youtube') {
            $youtube_link = filter_var($_POST['youtube_link'], FILTER_VALIDATE_URL);
            if (!$youtube_link) {
                throw new Exception('Invalid YouTube URL');
            }

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', $content_type, $youtube_link, $display_duration, $loop_count, $new_display_order]);
        } elseif ($content_type === 'video_upload') {
            // Handle MP4 video upload
            if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid MP4 video file');
            }

            $file = $_FILES['video_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file_ext !== 'mp4') {
                throw new Exception('File must be MP4 format. Detected: ' . $file_ext);
            }

            // Check file size - InfinityFree has 10MB limit
            $fileSize = $file['size'];
            $maxSize = 10 * 1024 * 1024;
            if ($fileSize > $maxSize) {
                throw new Exception('Video file too large. InfinityFree allows maximum 10MB per file. Your file is ' . round($fileSize / 1024 / 1024, 2) . 'MB.');
            }

            $allowed_files = ['mp4'];
            $file_path = validateAndUploadFile($file, '../uploads/videos/', $allowed_files);
            $file_path = str_replace('//', '/', $file_path);

            $content_data = json_encode([
                'type' => 'local_video',
                'file_path' => $file_path
            ]);

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', 'local_video', $description, $content_data, $display_duration, $loop_count, $new_display_order]);
        } elseif ($content_type === 'website') {
            // Handle website URL embedding
            $website_url = filter_var($_POST['website_url'], FILTER_VALIDATE_URL);
            if (!$website_url) {
                throw new Exception('Invalid website URL');
            }

            // Extract hostname for display
            $hostname = parse_url($website_url, PHP_URL_HOST);
            $display_title = !empty($description) ? $description : $hostname;

            $content_data = json_encode([
                'type' => 'website',
                'type' => 'website',
                'url' => $website_url,
                'title' => $display_title
            ]);

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', 'website', $description, $content_data, $display_duration, 1, $new_display_order]);
        } elseif ($content_type === 'audio_upload') {
            // Handle audio file upload
            if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid audio file');
            }

            $file = $_FILES['audio_file'];
            $allowed_files = ['mp3', 'wav', 'ogg', 'm4a'];
            $file_path = validateAndUploadFile($file, '../uploads/audio/', $allowed_files);

            $audio_title = isset($_POST['audio_title']) ? htmlspecialchars($_POST['audio_title']) : '';
            $show_waveform = isset($_POST['show_waveform']) ? 1 : 0;

            $content_data = json_encode([
                'type' => 'local_audio',
                'file_path' => $file_path,
                'title' => $audio_title,
                'show_waveform' => $show_waveform
            ]);

            $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, description, content_data, display_duration, loop_count, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute(['lmt', 'local_audio', $description, $content_data, $display_duration, $loop_count, $new_display_order]);
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

        if ($make_first) {
            $stmt_extra = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt_extra->execute();
            header('Location: /');
            exit();
        } else {
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
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

// Get all content ordered by display_order
$stmt = $pdo->prepare("SELECT id, content_type, description, created_at, display_order 
                       FROM content 
                       WHERE admin_role = 'lmt' 
                       ORDER BY display_order ASC, id ASC");
$stmt->execute();
$all_content = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMT Admin Panel - ET TV</title>
    <link rel="icon" type="image/png" href="../img/ethiopian_logo.ico">
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
        .pdf-inputs,
        .ppt-inputs,
        .video-upload,
        .audio-upload,
        .website-inputs {
            display: none;
        }

        .image-inputs.active,
        .pdf-inputs.active,
        .ppt-inputs.active,
        .video-upload.active,
        .audio-upload.active,
        .website-inputs.active {
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

        .btn-bulk-upload {
            background: #17a2b8;
            margin-left: 10px;
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

        .custom-duration-input {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .custom-duration-input input {
            width: 150px;
            margin: 0;
        }

        .custom-duration-input span {
            color: #666;
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

        .audio-options {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .audio-options label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .audio-options input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
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
                        <option value="video_upload">🎬 Upload MP4 Video</option>
                        <option value="audio_upload">🎵 Upload Audio (MP3, WAV, OGG)</option>
                        <option value="website">🌐 Website Embed (URL)</option>
                        <option value="message">💬 Custom Message</option>
                        <option value="pdf">📑 PDF Document</option>
                    </select>
                </div>

                <!-- Description Field -->
                <div class="form-group description-field" id="descriptionField">
                    <label>📝 Description (Optional)</label>
                    <textarea name="description" id="description" placeholder="Enter a description for this content" maxlength="500"></textarea>
                    <div class="description-hint">This description will help you identify this content in the order manager</div>
                </div>

                <!-- Position Selection -->
                <div class="form-group">
                    <label>📍 Display Position</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="make_first" id="makeFirst" value="1">
                        <label for="makeFirst">⭐ Make this the FIRST display content</label>
                        <small>TV will refresh to show this content immediately</small>
                    </div>
                    <div id="insertPositionGroup" class="insert-position-group">
                        <label>📌 Insert after position:</label>
                        <select name="insert_position" id="insertPosition">
                            <option value="last">-- At the end (after all existing content) --</option>
                            <?php $position = 1;
                            foreach ($all_content as $content): ?>
                                <option value="<?php echo $content['display_order']; ?>">
                                    After #<?php echo $position; ?> -
                                    <?php
                                    $type_icon = '';
                                    if ($content['content_type'] === 'slideshow') $type_icon = '🖼️';
                                    elseif ($content['content_type'] === 'youtube') $type_icon = '▶️';
                                    elseif ($content['content_type'] === 'message') $type_icon = '💬';
                                    elseif ($content['content_type'] === 'local_audio') $type_icon = '🎵';
                                    elseif ($content['content_type'] === 'local_video') $type_icon = '🎬';
                                    elseif ($content['content_type'] === 'website') $type_icon = '🌐';
                                    elseif ($content['content_type'] === 'pdf') $type_icon = '📑';
                                    elseif ($content['content_type'] === 'ppt') $type_icon = '📊';
                                    else $type_icon = '📄';
                                    echo $type_icon . ' ' . ucfirst(str_replace('local_', '', $content['content_type']));
                                    if (!empty($content['description'])) {
                                        echo ' - ' . htmlspecialchars(substr($content['description'], 0, 30));
                                    }
                                    ?>
                                </option>
                            <?php $position++;
                            endforeach; ?>
                        </select>
                        <div class="info-text">New content will appear AFTER the selected position in the display sequence.</div>
                    </div>
                </div>

                <!-- Layout Type for Slideshow -->
                <div class="form-group" id="layoutTypeGroup" style="display: none;">
                    <label>Display Layout</label>
                    <select name="layout_type" id="layoutType">
                        <option value="slideshow">Slideshow (One image at a time)</option>
                        <option value="2-image">2 Images Side by Side</option>
                        <option value="3-image">3 Images Side by Side</option>
                        <option value="4-image">4 Images Grid (2x2)</option>
                    </select>
                    <div class="layout-preview" id="layoutPreview">💡 Selected layout will display all images at once</div>
                </div>

                <!-- Slideshow Upload -->
                <div id="slideshowUpload" class="image-inputs">
                    <label>📸 Upload Images (Bulk upload supported)</label>
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
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn-add-image" onclick="addImage()">+ Add Another Image</button>
                        <button type="button" class="btn-bulk-upload" onclick="bulkUpload()">📁 Bulk Upload (Multiple Files)</button>
                    </div>
                    <div class="info-text">Supported formats: JPG, PNG, GIF, BMP (Max 10MB each). <strong>Hold Ctrl/Cmd to select multiple files.</strong></div>
                </div>

                <!-- PDF Upload -->
                <div id="pdfUpload" class="pdf-inputs">
                    <div class="form-group">
                        <label>📑 PDF Document</label>
                        <input type="file" name="pdf_file" accept=".pdf">
                        <div class="info-text">Upload PDF document. The document will be displayed page by page.</div>
                    </div>
                </div>

                <!-- PowerPoint Upload -->
                <div id="pptUploadSection" class="ppt-inputs">
                    <div class="form-group">
                        <label>📊 PowerPoint File</label>
                        <input type="file" name="ppt_file" accept=".ppt,.pptx">
                        <div class="info-text">Upload PowerPoint file (PPT/PPTX). <strong>Note:</strong> Convert to PDF for better compatibility if issues occur.</div>
                    </div>
                </div>

                <!-- Website Embed -->
                <div id="websiteInputs" class="website-inputs">
                    <div class="form-group">
                        <label>🌐 Website URL</label>
                        <input type="url" name="website_url" placeholder="https://example.com">
                        <div class="info-text">Enter the full URL of the website you want to display on TV.</div>
                    </div>
                    <div class="info-text">💡 The website will be displayed in an iframe. Some websites may block embedding.</div>
                </div>

                <!-- YouTube Embed -->
                <div id="youtubeLink" class="image-inputs">
                    <div class="form-group">
                        <label>🎬 YouTube URL (Embed)</label>
                        <input type="url" name="youtube_link" placeholder="https://www.youtube.com/watch?v=...">
                        <div class="info-text">Video will play embedded.</div>
                    </div>
                </div>

                <!-- MP4 Video Upload -->
                <div id="videoUpload" class="video-upload">
                    <div class="form-group">
                        <label>🎬 Upload MP4 Video</label>
                        <input type="file" name="video_file" accept="video/mp4">
                        <div class="info-text">⚠️ <strong>Max 10MB per file.</strong> Compress videos using HandBrake.</div>
                    </div>
                </div>

                <!-- Audio Upload -->
                <div id="audioUpload" class="audio-upload">
                    <div class="form-group">
                        <label>🎵 Upload Audio File</label>
                        <input type="file" name="audio_file" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4">
                        <div class="info-text">Supported formats: MP3, WAV, OGG, M4A (Max 10MB)</div>
                    </div>
                    <div class="audio-options">
                        <div class="form-group">
                            <label>🎤 Audio Title (Optional)</label>
                            <input type="text" name="audio_title" placeholder="e.g., 'Background Music'">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="show_waveform" value="1" checked> 🎵 Show Audio Waveform Visualization</label>
                        </div>
                    </div>
                </div>

                <!-- Custom Message -->
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

                <!-- Overall Display Duration -->
                <div class="form-group">
                    <label>⏱️ Overall Display Duration</label>
                    <select name="display_duration" id="displayDurationSelect" class="duration-select">
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
                        <option value="custom">Custom (enter seconds)</option>
                    </select>
                    <div class="custom-duration-input" id="customDurationInput" style="display: none;">
                        <input type="number" name="display_duration_custom" id="displayDurationCustom" placeholder="Enter seconds" min="1" max="86400">
                        <span>seconds (e.g., 300 = 5 minutes)</span>
                    </div>
                </div>

                <!-- Loop Group for Videos -->
                <div class="form-group" id="loopGroup" style="display: none;">
                    <label>🔄 Loop Count (for videos)</label>
                    <input type="number" name="loop_count" value="1" min="1" max="100">
                </div>

                <button type="submit">🚀 Publish to TV</button>
            </form>
        </div>
    </div>

    <script>
        let imageCount = 1;

        const contentType = document.getElementById('contentType');
        const slideshowUpload = document.getElementById('slideshowUpload');
        const pdfUpload = document.getElementById('pdfUpload');
        const pptUploadSection = document.getElementById('pptUploadSection');
        const youtubeLink = document.getElementById('youtubeLink');
        const videoUpload = document.getElementById('videoUpload');
        const audioUpload = document.getElementById('audioUpload');
        const websiteInputs = document.getElementById('websiteInputs');
        const customMessage = document.getElementById('customMessage');
        const loopGroup = document.getElementById('loopGroup');
        const layoutTypeGroup = document.getElementById('layoutTypeGroup');
        const layoutType = document.getElementById('layoutType');
        const descriptionField = document.getElementById('descriptionField');
        const makeFirst = document.getElementById('makeFirst');
        const insertPositionGroup = document.getElementById('insertPositionGroup');
        const displayDurationSelect = document.getElementById('displayDurationSelect');
        const customDurationInput = document.getElementById('customDurationInput');
        const displayDurationCustom = document.getElementById('displayDurationCustom');

        displayDurationSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDurationInput.style.display = 'flex';
                displayDurationCustom.required = true;
            } else {
                customDurationInput.style.display = 'none';
                displayDurationCustom.required = false;
                displayDurationCustom.value = '';
            }
        });

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
            if (selectedType === 'slideshow' || selectedType === 'pdf' || selectedType === 'audio_upload' || selectedType === 'video_upload' || selectedType === 'website') {
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

        function bulkUpload() {
            const bulkInput = document.createElement('input');
            bulkInput.type = 'file';
            bulkInput.multiple = true;
            bulkInput.accept = 'image/jpeg,image/png,image/gif,image/bmp';
            bulkInput.onchange = function(e) {
                const files = Array.from(e.target.files);
                files.forEach((file) => {
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

                    const fileInput = imageDiv.querySelector('input[type="file"]');
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;

                    container.appendChild(imageDiv);
                    imageCount++;
                });
                updateDurationVisibility();
            };
            bulkInput.click();
        }

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

        contentType.addEventListener('change', function() {
            slideshowUpload.classList.remove('active');
            pdfUpload.classList.remove('active');
            pptUploadSection.classList.remove('active');
            youtubeLink.classList.remove('active');
            videoUpload.classList.remove('active');
            audioUpload.classList.remove('active');
            websiteInputs.classList.remove('active');
            customMessage.classList.remove('active');

            updateDescriptionVisibility();

            if (this.value === 'slideshow') {
                slideshowUpload.classList.add('active');
                layoutTypeGroup.style.display = 'block';
                loopGroup.style.display = 'none';
                updateDurationVisibility();
            } else if (this.value === 'pdf') {
                pdfUpload.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else if (this.value === 'youtube') {
                youtubeLink.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'block';
            } else if (this.value === 'video_upload') {
                videoUpload.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else if (this.value === 'audio_upload') {
                audioUpload.classList.add('active');
                layoutTypeGroup.style.display = 'none';
                loopGroup.style.display = 'none';
            } else if (this.value === 'website') {
                websiteInputs.classList.add('active');
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
                    if (input.files && input.files.length > 0) hasFiles = true;
                });
                if (!hasFiles) {
                    alert('Please upload at least one image.');
                    e.preventDefault();
                    return;
                }
            } else if (type === 'pdf') {
                const pdfFile = document.querySelector('input[name="pdf_file"]');
                if (!pdfFile || !pdfFile.files.length) {
                    alert('Please upload a PDF file.');
                    e.preventDefault();
                }
            } else if (type === 'youtube') {
                const youtubeLink = document.querySelector('input[name="youtube_link"]');
                if (!youtubeLink || !youtubeLink.value.trim()) {
                    alert('Please enter a YouTube URL.');
                    e.preventDefault();
                }
            } else if (type === 'video_upload') {
                const videoFile = document.querySelector('input[name="video_file"]');
                if (!videoFile || !videoFile.files.length) {
                    alert('Please upload an MP4 video file.');
                    e.preventDefault();
                    return;
                }
                const fileName = videoFile.files[0].name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                if (fileExt !== 'mp4') {
                    alert('Only MP4 files are allowed. Detected: ' + fileExt.toUpperCase());
                    e.preventDefault();
                    return;
                }
                const fileSize = videoFile.files[0].size;
                const maxSize = 10 * 1024 * 1024;
                if (fileSize > maxSize) {
                    alert('File too large. Max 10MB allowed.\n\nYour file: ' + (fileSize / 1024 / 1024).toFixed(2) + 'MB');
                    e.preventDefault();
                    return;
                }
            } else if (type === 'audio_upload') {
                const audioFile = document.querySelector('input[name="audio_file"]');
                if (!audioFile || !audioFile.files.length) {
                    alert('Please upload an audio file.');
                    e.preventDefault();
                }
            } else if (type === 'website') {
                const websiteUrl = document.querySelector('input[name="website_url"]');
                if (!websiteUrl || !websiteUrl.value.trim()) {
                    alert('Please enter a website URL.');
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