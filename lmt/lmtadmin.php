<?php
require_once '../config/db.php';
require_once '../includes/upload_handler.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('Location: ../admin/login.php');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $content_type = $_POST['content_type'];
            $display_duration = convertToSeconds($_POST['display_duration']);
            $loop_count = isset($_POST['loop_count']) ? (int)$_POST['loop_count'] : 1;
            $next_content_id = !empty($_POST['next_content_id']) ? (int)$_POST['next_content_id'] : null;

            // Validate content type
            $allowed_types = ['slideshow', 'youtube', 'message', 'ppt'];
            if (!in_array($content_type, $allowed_types)) {
                throw new Exception('Invalid content type');
            }

            // Start transaction
            $pdo->beginTransaction();

            if ($content_type === 'slideshow') {
                // Handle slideshow with multiple images
                if (!isset($_FILES['images']) || empty($_FILES['images']['tmp_name'][0])) {
                    throw new Exception('Please upload at least one image');
                }

                // Insert content first
                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, display_duration, loop_count, next_content_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute(['lmt', $content_type, $display_duration, $loop_count, $next_content_id]);
                $content_id = $pdo->lastInsertId();

                // Process each image
                $slide_order = 0;
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $_FILES['images']['error'][$key],
                            'size' => $_FILES['images']['size'][$key]
                        ];

                        $image_path = validateAndUploadFile($file, '../uploads/', ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                        $duration = isset($_POST["duration_$key"]) ? (int)$_POST["duration_$key"] : 10;

                        $stmt2 = $pdo->prepare("INSERT INTO content_slides (content_id, image_path, duration, slide_order) VALUES (?, ?, ?, ?)");
                        $stmt2->execute([$content_id, $image_path, $duration, $slide_order]);
                        $slide_order++;
                    }
                }
            } elseif ($content_type === 'ppt') {
                // Handle PPT upload
                if (!isset($_FILES['ppt_file']) || $_FILES['ppt_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please upload a valid PowerPoint file');
                }

                $file = $_FILES['ppt_file'];
                $allowed_ppt = ['ppt', 'pptx', 'pdf'];
                $image_path = validateAndUploadFile($file, '../uploads/', $allowed_ppt);

                $content_data = json_encode(['file_path' => $image_path]);

                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, display_duration, loop_count, next_content_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute(['lmt', $content_type, $content_data, $display_duration, $loop_count, $next_content_id]);
            } elseif ($content_type === 'youtube') {
                $youtube_link = filter_var($_POST['youtube_link'], FILTER_VALIDATE_URL);
                if (!$youtube_link) {
                    throw new Exception('Invalid YouTube URL');
                }

                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, display_duration, loop_count, next_content_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute(['lmt', $content_type, $youtube_link, $display_duration, $loop_count, $next_content_id]);
            } elseif ($content_type === 'message') {
                $message_text = htmlspecialchars($_POST['message_text'], ENT_QUOTES, 'UTF-8');
                $message_type = $_POST['message_type'];

                $allowed_types = ['warning', 'caution', 'memo', 'congratulation'];
                if (!in_array($message_type, $allowed_types)) {
                    $message_type = 'memo';
                }

                $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, message_type, display_duration, loop_count, next_content_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute(['lmt', $content_type, $message_text, $message_type, $display_duration, $loop_count, $next_content_id]);
            }

            // Set previous content as inactive
            $stmt2 = $pdo->prepare("UPDATE content SET is_active = 0 WHERE admin_role = 'lmt' AND is_active = 1 AND id != ?");
            $stmt2->execute([$pdo->lastInsertId()]);

            // Update version for real-time refresh
            $stmt3 = $pdo->prepare("UPDATE content_version SET version = version + 1, last_update = NOW() WHERE admin_role = 'lmt'");
            $stmt3->execute();

            $pdo->commit();
            $success = "Content published successfully! All TV displays will update in real-time.";

            // Regenerate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
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

// Get existing content for dropdown
$stmt = $pdo->prepare("SELECT id, content_type, created_at FROM content WHERE admin_role = 'lmt' AND is_active = 0 ORDER BY created_at DESC LIMIT 50");
$stmt->execute();
$existing_content = $stmt->fetchAll();
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
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
            margin-bottom: 30px;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
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
            min-height: 100px;
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

        .message-type-select {
            margin-top: 10px;
        }

        .duration-select {
            width: auto;
            min-width: 200px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>LMT Admin Dashboard</h1>
        <a href="../admin/logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2>Create New Display Content</h2>
            <form method="POST" enctype="multipart/form-data" id="contentForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label>Content Type</label>
                    <select name="content_type" id="contentType" required>
                        <option value="">Select Content Type</option>
                        <option value="slideshow">Slideshow (Images)</option>
                        <option value="youtube">YouTube Video</option>
                        <option value="message">Custom Message</option>
                        <option value="ppt">PowerPoint Presentation</option>
                    </select>
                </div>

                <!-- Slideshow Upload -->
                <div id="slideshowUpload" class="image-inputs">
                    <label>Images with Individual Durations</label>
                    <div id="imagesContainer">
                        <div class="image-item" data-index="0">
                            <label>Image 1</label>
                            <input type="file" name="images[]" accept="image/*" required>
                            <div class="duration-input">
                                <label>Display duration for this image:</label>
                                <input type="number" name="duration_0" value="10" min="1" max="3600" step="1"> seconds
                            </div>
                            <button type="button" class="btn-remove-image" onclick="removeImage(this)" style="display:none;">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-image" onclick="addImage()">+ Add Another Image</button>
                    <div class="info-text">You can add unlimited images (max 10MB each). Set individual display time for each image.</div>
                </div>

                <!-- PPT Upload -->
                <div id="pptUpload" class="ppt-inputs">
                    <div class="form-group">
                        <label>PowerPoint File (.ppt, .pptx, or .pdf)</label>
                        <input type="file" name="ppt_file" accept=".ppt,.pptx,.pdf">
                        <div class="info-text">Upload PowerPoint presentation. For best results, convert to PDF first. Max file size: 50MB.</div>
                    </div>
                </div>

                <!-- YouTube Link -->
                <div id="youtubeLink" class="image-inputs">
                    <div class="form-group">
                        <label>YouTube URL</label>
                        <input type="url" name="youtube_link" placeholder="https://www.youtube.com/watch?v=...">
                        <div class="info-text">Paste any YouTube video link</div>
                    </div>
                </div>

                <!-- Custom Message -->
                <div id="customMessage" class="image-inputs">
                    <div class="form-group">
                        <label>Message Text</label>
                        <textarea name="message_text" placeholder="Enter your message here..." maxlength="500"></textarea>
                    </div>
                    <div class="form-group message-type-select">
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
                    <label>Overall Display Duration (for non-slideshow content)</label>
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
                        <option value="24h">24 hours (Full day)</option>
                    </select>
                    <div class="info-text">How long to show this content before moving to next content</div>
                </div>

                <div class="form-group" id="loopGroup" style="display: none;">
                    <label>Loop Count (for videos)</label>
                    <input type="number" name="loop_count" value="1" min="1" max="100">
                    <div class="info-text">How many times to loop the video</div>
                </div>

                <div class="form-group">
                    <label>Next Content (after expiry)</label>
                    <select name="next_content_id">
                        <option value="">Default Display</option>
                        <?php foreach ($existing_content as $content): ?>
                            <option value="<?php echo $content['id']; ?>">
                                <?php echo ucfirst($content['content_type']) . " - " . date('Y-m-d H:i', strtotime($content['created_at'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="info-text">Select what to show after this content expires</div>
                </div>

                <button type="submit">Publish to TV</button>
            </form>
        </div>
    </div>

    <script>
        let imageCount = 1;

        const contentType = document.getElementById('contentType');
        const slideshowUpload = document.getElementById('slideshowUpload');
        const pptUpload = document.getElementById('pptUpload');
        const youtubeLink = document.getElementById('youtubeLink');
        const customMessage = document.getElementById('customMessage');
        const loopGroup = document.getElementById('loopGroup');

        contentType.addEventListener('change', function() {
            slideshowUpload.classList.remove('active');
            pptUpload.classList.remove('active');
            youtubeLink.classList.remove('active');
            customMessage.classList.remove('active');

            if (this.value === 'slideshow') {
                slideshowUpload.classList.add('active');
                loopGroup.style.display = 'none';
            } else if (this.value === 'ppt') {
                pptUpload.classList.add('active');
                loopGroup.style.display = 'none';
            } else if (this.value === 'youtube') {
                youtubeLink.classList.add('active');
                loopGroup.style.display = 'block';
            } else if (this.value === 'message') {
                customMessage.classList.add('active');
                loopGroup.style.display = 'none';
            } else {
                loopGroup.style.display = 'none';
            }
        });

        function addImage() {
            const container = document.getElementById('imagesContainer');
            const newIndex = imageCount;

            const imageDiv = document.createElement('div');
            imageDiv.className = 'image-item';
            imageDiv.setAttribute('data-index', newIndex);
            imageDiv.innerHTML = `
                <label>Image ${newIndex + 1}</label>
                <input type="file" name="images[]" accept="image/*" required>
                <div class="duration-input">
                    <label>Display duration for this image:</label>
                    <input type="number" name="duration_${newIndex}" value="10" min="1" max="3600" step="1"> seconds
                </div>
                <button type="button" class="btn-remove-image" onclick="removeImage(this)">Remove</button>
            `;

            container.appendChild(imageDiv);
            imageCount++;
        }

        function removeImage(button) {
            const imageItem = button.parentElement;
            imageItem.remove();
            // Re-index remaining images
            const remainingImages = document.querySelectorAll('.image-item');
            remainingImages.forEach((img, idx) => {
                img.setAttribute('data-index', idx);
                const label = img.querySelector('label');
                if (label) label.textContent = `Image ${idx + 1}`;

                const durationInput = img.querySelector('.duration-input input');
                if (durationInput && durationInput.name) {
                    durationInput.name = `duration_${idx}`;
                }
            });
            imageCount = remainingImages.length;
        }

        // Form validation
        document.getElementById('contentForm').addEventListener('submit', function(e) {
            const type = contentType.value;
            if (type === 'slideshow') {
                const fileInputs = document.querySelectorAll('#imagesContainer input[type="file"]');
                let hasFiles = false;
                fileInputs.forEach(input => {
                    if (input.files.length > 0) hasFiles = true;
                });
                if (!hasFiles) {
                    alert('Please upload at least one image for the slideshow.');
                    e.preventDefault();
                }
            } else if (type === 'ppt') {
                const pptFile = document.querySelector('input[name="ppt_file"]');
                if (!pptFile.files.length) {
                    alert('Please upload a PowerPoint file.');
                    e.preventDefault();
                }
            } else if (type === 'youtube') {
                const youtubeLink = document.querySelector('input[name="youtube_link"]');
                if (!youtubeLink.value) {
                    alert('Please enter a YouTube URL.');
                    e.preventDefault();
                }
            } else if (type === 'message') {
                const messageText = document.querySelector('textarea[name="message_text"]');
                if (!messageText.value.trim()) {
                    alert('Please enter a message.');
                    e.preventDefault();
                }
            }
        });
    </script>
</body>

</html>