<?php
require_once 'config/db.php';

// Get current display mode from session or default to lmt
$current_mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_SESSION['display_mode']) ? $_SESSION['display_mode'] : 'lmt');
if (isset($_GET['mode'])) {
    $_SESSION['display_mode'] = $current_mode;
}

// Get current content for display
$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$current_mode]);
$current_content = $stmt->fetch();

// If no active content, get default
if (!$current_content) {
    $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
    $stmt->execute([$current_mode]);
    $default = $stmt->fetch();
    $current_content = [
        'content_type' => $default['default_content_type'],
        'content_data' => $default['default_content_data'],
        'message_type' => $default['default_message_type'],
        'display_duration' => 10,
        'loop_count' => 1
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>ET TV Display - <?php echo strtoupper($current_mode); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow: hidden;
        }

        /* Navigation Dropdown - Left Side */
        .nav-dropdown {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            transform: translateX(-98%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .nav-dropdown:hover {
            transform: translateX(0);
        }

        .nav-dropdown.active {
            transform: translateX(0);
            background: rgba(0, 0, 0, 0.95);
        }

        .nav-content {
            padding: 20px;
            color: white;
        }

        .nav-content h3 {
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .mode-option {
            display: block;
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .mode-option:hover {
            transform: scale(1.05);
        }

        /* Main Display Area */
        .display-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .content-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Image Slideshow */
        .slideshow-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #000;
        }

        .slide-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            position: absolute;
            opacity: 0;
            transition: opacity 1s ease;
        }

        .slide-image.active {
            opacity: 1;
        }

        /* YouTube Video */
        .video-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .video-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Custom Message Styles */
        .message-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .message-card {
            max-width: 90%;
            padding: 60px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-left: 10px solid #ff0000;
        }

        .caution {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            border-left: 10px solid #ff9800;
        }

        .memo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-left: 10px solid #2196f3;
        }

        .congratulation {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-left: 10px solid #4caf50;
            color: #333;
        }

        .message-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }

        .message-text {
            font-size: 48px;
            line-height: 1.4;
            font-weight: bold;
        }

        .warning .message-text {
            color: #fff;
        }

        .caution .message-text {
            color: #fff;
        }

        .memo .message-text {
            color: #fff;
        }

        .congratulation .message-text {
            color: #333;
        }

        /* Current mode indicator */
        .mode-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            z-index: 999;
        }

        @media (max-width: 768px) {
            .message-text {
                font-size: 24px;
            }

            .message-card {
                padding: 30px;
            }

            .message-icon {
                font-size: 50px;
            }
        }
    </style>
</head>

<body>
    <div class="nav-dropdown" id="navDropdown">
        <div class="nav-content">
            <h3>Select Display</h3>
            <button class="mode-option" onclick="switchMode('lmt')">LMT Display</button>
            <button class="mode-option" onclick="switchMode('bmt')">BMT Display</button>
        </div>
    </div>

    <div class="display-container">
        <div class="content-wrapper" id="contentWrapper">
            <!-- Content will be loaded here -->
        </div>
    </div>

    <div class="mode-indicator" id="modeIndicator">
        Current: <?php echo strtoupper($current_mode); ?>
    </div>

    <script>
        let currentMode = '<?php echo $current_mode; ?>';
        let currentContent = <?php echo json_encode($current_content); ?>;
        let slideIndex = 0;
        let slideshowInterval = null;
        let videoLoopCount = 0;
        let videoPlayer = null;

        // Load content on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadContent(currentContent);
            startAutoRefresh();
        });

        // Nav dropdown hover functionality
        const navDropdown = document.getElementById('navDropdown');
        let hoverTimer;

        navDropdown.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimer);
            this.style.transform = 'translateX(0)';
            this.style.background = 'rgba(0, 0, 0, 0.4)';
        });

        navDropdown.addEventListener('mouseleave', function() {
            hoverTimer = setTimeout(() => {
                if (!navDropdown.classList.contains('active')) {
                    navDropdown.style.transform = 'translateX(-98%)';
                }
            }, 1000);
        });

        navDropdown.addEventListener('click', function(e) {
            if (e.target.classList.contains('mode-option')) {
                this.classList.add('active');
                this.style.background = 'rgba(0, 0, 0, 0.95)';
            } else {
                this.classList.toggle('active');
                if (this.classList.contains('active')) {
                    this.style.background = 'rgba(0, 0, 0, 0.95)';
                    this.style.transform = 'translateX(0)';
                } else {
                    this.style.background = 'rgba(0, 0, 0, 0.4)';
                    this.style.transform = 'translateX(-98%)';
                }
            }
        });

        function switchMode(mode) {
            if (mode === currentMode) return;
            currentMode = mode;
            document.getElementById('modeIndicator').innerHTML = `Current: ${mode.toUpperCase()}`;

            // Fetch new content for the selected mode
            fetch(`get_content.php?mode=${mode}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    currentContent = data;
                    loadContent(currentContent);
                })
                .catch(error => console.error('Error:', error));

            // Close dropdown
            navDropdown.classList.remove('active');
            navDropdown.style.transform = 'translateX(-98%)';
        }

        function loadContent(content) {
            const wrapper = document.getElementById('contentWrapper');

            // Clear any existing intervals
            if (slideshowInterval) clearInterval(slideshowInterval);

            switch (content.content_type) {
                case 'slide':
                    loadSlideshow(content);
                    break;
                case 'youtube':
                    loadYouTube(content);
                    break;
                case 'message':
                    loadMessage(content);
                    break;
                default:
                    loadMessage(content);
            }
        }

        function loadSlideshow(content) {
            const wrapper = document.getElementById('contentWrapper');
            const images = JSON.parse(content.content_data);

            let html = '<div class="slideshow-container">';
            images.forEach((img, index) => {
                html += `<img src="${img}" class="slide-image ${index === 0 ? 'active' : ''}" data-index="${index}">`;
            });
            html += '</div>';
            wrapper.innerHTML = html;

            slideIndex = 0;
            const slides = document.querySelectorAll('.slide-image');

            if (slideshowInterval) clearInterval(slideshowInterval);

            slideshowInterval = setInterval(() => {
                slides[slideIndex].classList.remove('active');
                slideIndex = (slideIndex + 1) % slides.length;
                slides[slideIndex].classList.add('active');
            }, content.display_duration * 1000);
        }

        function loadYouTube(content) {
            const wrapper = document.getElementById('contentWrapper');
            const videoId = extractYouTubeId(content.content_data);

            wrapper.innerHTML = `
                <div class="video-container">
                    <iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&enablejsapi=1" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                    </iframe>
                </div>
            `;

            // Handle video looping
            videoLoopCount = 0;
            const iframe = wrapper.querySelector('iframe');

            // Listen for video end events
            window.addEventListener('message', function(event) {
                if (event.data === 'ended') {
                    videoLoopCount++;
                    if (videoLoopCount < content.loop_count) {
                        // Reload video
                        iframe.src = iframe.src;
                    } else {
                        // Load next content or default
                        loadNextContent(content);
                    }
                }
            });

            // Set timeout as backup
            setTimeout(() => {
                if (videoLoopCount < content.loop_count) {
                    videoLoopCount++;
                    if (videoLoopCount >= content.loop_count) {
                        loadNextContent(content);
                    }
                }
            }, content.display_duration * 1000 * content.loop_count);
        }

        function loadMessage(content) {
            const wrapper = document.getElementById('contentWrapper');
            const messageType = content.message_type || 'memo';
            const messageText = content.content_data;

            let icon = '';
            switch (messageType) {
                case 'warning':
                    icon = '⚠️';
                    break;
                case 'caution':
                    icon = '⚡';
                    break;
                case 'memo':
                    icon = '📝';
                    break;
                case 'congratulation':
                    icon = '🎉';
                    break;
            }

            wrapper.innerHTML = `
                <div class="message-container">
                    <div class="message-card ${messageType}">
                        <div class="message-icon">${icon}</div>
                        <div class="message-text">${messageText}</div>
                    </div>
                </div>
            `;

            // Auto refresh to next content if duration set
            if (content.display_duration && content.display_duration > 0) {
                setTimeout(() => {
                    loadNextContent(content);
                }, content.display_duration * 1000);
            }
        }

        function loadNextContent(currentContent) {
            if (currentContent.next_content_id) {
                fetch(`get_content.php?id=${currentContent.next_content_id}`)
                    .then(response => response.json())
                    .then(content => {
                        loadContent(content);
                    });
            } else {
                // Load default content
                fetch(`get_content.php?mode=${currentMode}&default=true`)
                    .then(response => response.json())
                    .then(content => {
                        loadContent(content);
                    });
            }
        }

        function extractYouTubeId(url) {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : url;
        }

        function startAutoRefresh() {
            // Check for new content every 5 seconds
            setInterval(() => {
                fetch(`check_update.php?mode=${currentMode}&timestamp=${Date.now()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated) {
                            // Force refresh to accept new display
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error checking updates:', error));
            }, 5000);
        }
    </script>
</body>

</html>