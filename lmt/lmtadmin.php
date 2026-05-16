<?php
require_once '../config/db.php';

// Check if user is logged in and has lmtadmin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lmtadmin') {
    header('Location: ../admin/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_type = $_POST['content_type'];
    $display_duration = $_POST['display_duration'];
    $loop_count = $_POST['loop_count'] ?? 1;
    $next_content_id = $_POST['next_content_id'] ?: null;

    if ($content_type === 'slide') {
        $images = [];
        for ($i = 1; $i <= 5; $i++) {
            if (isset($_FILES["image_$i"]) && $_FILES["image_$i"]['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

                $filename = time() . '_' . $_FILES["image_$i"]['name'];
                move_uploaded_file($_FILES["image_$i"]['tmp_name'], $upload_dir . $filename);
                $images[] = 'uploads/' . $filename;
            }
        }
        $content_data = json_encode($images);
        $message_type = null;
    } elseif ($content_type === 'youtube') {
        $content_data = $_POST['youtube_link'];
        $message_type = null;
    } elseif ($content_type === 'message') {
        $content_data = $_POST['message_text'];
        $message_type = $_POST['message_type'];
    }

    // Fixed SQL query - removed the extra parameter
    $stmt = $pdo->prepare("INSERT INTO content (admin_role, content_type, content_data, display_duration, loop_count, next_content_id, message_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$admin_role = 'lmt', $content_type, $content_data, $display_duration, $loop_count, $next_content_id, $message_type]);

    // Set previous content as inactive
    $stmt2 = $pdo->prepare("UPDATE content SET is_active = 0 WHERE admin_role = 'lmt' AND id != ?");
    $stmt2->execute([$pdo->lastInsertId()]);

    $success = "Content published successfully! All TV displays will update shortly.";
}

// Get existing content for dropdown
$stmt = $pdo->prepare("SELECT id, content_type, created_at FROM content WHERE admin_role = 'lmt' ORDER BY created_at DESC");
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
            max-width: 800px;
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

        .image-inputs {
            display: none;
        }

        .image-inputs.active {
            display: block;
        }

        .image-item {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 10px;
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

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .message-type-select {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>LMT Admin Dashboard</h1>
        <a href="../admin/logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2>Create New Display Content</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Content Type</label>
                    <select name="content_type" id="contentType" required>
                        <option value="">Select Content Type</option>
                        <option value="slide">Slide Show (Images)</option>
                        <option value="youtube">YouTube Video</option>
                        <option value="message">Custom Message</option>
                    </select>
                </div>

                <!-- Slide Upload -->
                <div id="slideUpload" class="image-inputs">
                    <label>Upload Images (Max 5)</label>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="image-item">
                            <label>Image <?php echo $i; ?></label>
                            <input type="file" name="image_<?php echo $i; ?>" accept="image/*">
                        </div>
                    <?php endfor; ?>
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
                        <textarea name="message_text" placeholder="Enter your message here..."></textarea>
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
                    <label>Display Duration (seconds)</label>
                    <input type="number" name="display_duration" value="10" required>
                    <div class="info-text">How long to show this content</div>
                </div>

                <div class="form-group" id="loopGroup">
                    <label>Loop Count (for videos)</label>
                    <input type="number" name="loop_count" value="1">
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
        const contentType = document.getElementById('contentType');
        const slideUpload = document.getElementById('slideUpload');
        const youtubeLink = document.getElementById('youtubeLink');
        const customMessage = document.getElementById('customMessage');
        const loopGroup = document.getElementById('loopGroup');

        contentType.addEventListener('change', function() {
            slideUpload.classList.remove('active');
            youtubeLink.classList.remove('active');
            customMessage.classList.remove('active');

            if (this.value === 'slide') {
                slideUpload.classList.add('active');
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
    </script>
</body>

</html>