<?php
require_once 'config/db.php';

// Get current display mode
$current_mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_SESSION['display_mode']) ? $_SESSION['display_mode'] : 'lmt');
if (isset($_GET['mode'])) {
    $_SESSION['display_mode'] = $current_mode;
}

// Validate mode
if (!in_array($current_mode, ['lmt', 'bmt'])) {
    $current_mode = 'lmt';
}

// Get current content
$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$current_mode]);
$current_content = $stmt->fetch();

if (!$current_content) {
    $stmt = $pdo->prepare("SELECT * FROM default_settings WHERE admin_role = ?");
    $stmt->execute([$current_mode]);
    $default = $stmt->fetch();
    $current_content = [
        'id' => null,
        'content_type' => $default['default_content_type'],
        'content_data' => $default['default_content_data'],
        'message_type' => $default['default_message_type'],
        'display_duration' => 10,
        'loop_count' => 1,
        'slide_durations' => null
    ];
}

// Get slides for slideshow content
$slides = [];
if ($current_content['content_type'] === 'slideshow' && $current_content['id']) {
    $stmt = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
    $stmt->execute([$current_content['id']]);
    $slides = $stmt->fetchAll();
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
            background: #000000;
            min-height: 100vh;
            overflow: hidden;
        }

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

        .nav-dropdown:hover,
        .nav-dropdown.active {
            transform: translateX(0);
        }

        .nav-dropdown.active {
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

        .display-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: #000;
        }

        .content-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

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
            transition: opacity 0.5s ease-in-out;
        }

        .slide-image.active {
            opacity: 1;
        }

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

        .ppt-container {
            width: 100%;
            height: 100%;
            background: #000;
            position: relative;
        }

        .ppt-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

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

        .warning .message-text,
        .caution .message-text,
        .memo .message-text {
            color: #fff;
        }

        .congratulation .message-text {
            color: #333;
        }

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
        <div class="content-wrapper" id="contentWrapper"></div>
    </div>

    <div class="mode-indicator" id="modeIndicator">
        Current: <?php echo strtoupper($current_mode); ?>
    </div>

    <script>
        let currentMode = '<?php echo $current_mode; ?>';
        let currentContent = <?php echo json_encode($current_content); ?>;
        let currentSlides = <?php echo json_encode($slides); ?>;
        let currentVersion = <?php echo json_encode($current_version ?? 1); ?>;
        let currentTimeouts = [];
        let videoLoopCount = 0;
        let eventSource = null;

        function clearAllTimeouts() {
            currentTimeouts.forEach(timeout => clearTimeout(timeout));
            currentTimeouts = [];
        }

        function switchMode(mode) {
            if (mode === currentMode) return;
            currentMode = mode;
            document.getElementById('modeIndicator').innerHTML = `Current: ${mode.toUpperCase()}`;

            fetch(`get_content.php?mode=${mode}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    currentContent = data.content;
                    currentSlides = data.slides || [];
                    loadContent();
                })
                .catch(error => console.error('Error:', error));

            document.getElementById('navDropdown').classList.remove('active');
        }

        function loadContent() {
            const wrapper = document.getElementById('contentWrapper');
            clearAllTimeouts();

            switch (currentContent.content_type) {
                case 'slideshow':
                    loadSlideshow();
                    break;
                case 'youtube':
                    loadYouTube();
                    break;
                case 'message':
                    loadMessage();
                    break;
                case 'ppt':
                    loadPPT();
                    break;
                default:
                    loadMessage();
            }
        }

        function loadSlideshow() {
            const wrapper = document.getElementById('contentWrapper');

            if (!currentSlides || currentSlides.length === 0) {
                loadMessage();
                return;
            }

            let html = '<div class="slideshow-container">';
            currentSlides.forEach((slide, index) => {
                html += `<img src="${slide.image_path}" class="slide-image" data-index="${index}" style="opacity: ${index === 0 ? 1 : 0}">`;
            });
            html += '</div>';
            wrapper.innerHTML = html;

            let currentIndex = 0;
            const slides = document.querySelectorAll('.slide-image');

            function showNextSlide() {
                slides[currentIndex].style.opacity = '0';
                currentIndex = (currentIndex + 1) % slides.length;
                slides[currentIndex].style.opacity = '1';

                const duration = currentSlides[currentIndex]?.duration || 10;
                const timeoutId = setTimeout(showNextSlide, duration * 1000);
                currentTimeouts.push(timeoutId);
            }

            // Start slideshow with first slide duration
            const firstDuration = currentSlides[0]?.duration || 10;
            if (slides.length > 1) {
                const timeoutId = setTimeout(showNextSlide, firstDuration * 1000);
                currentTimeouts.push(timeoutId);
            }

            // Schedule content expiry if needed
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            const videoId = extractYouTubeId(currentContent.content_data);

            wrapper.innerHTML = `
                <div class="video-container">
                    <iframe id="youtubePlayer" src="https://www.youtube.com/embed/${videoId}?autoplay=1&enablejsapi=1" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                    </iframe>
                </div>
            `;

            videoLoopCount = 0;
            const totalDuration = currentContent.display_duration * currentContent.loop_count;

            function handleVideoEnd() {
                videoLoopCount++;
                if (videoLoopCount < currentContent.loop_count) {
                    const iframe = document.getElementById('youtubePlayer');
                    iframe.src = iframe.src;
                    const timeoutId = setTimeout(handleVideoEnd, totalDuration * 1000);
                    currentTimeouts.push(timeoutId);
                } else {
                    loadNextContent();
                }
            }

            const timeoutId = setTimeout(handleVideoEnd, totalDuration * 1000);
            currentTimeouts.push(timeoutId);
        }

        function loadPPT() {
            const wrapper = document.getElementById('contentWrapper');
            let pptData;

            try {
                pptData = JSON.parse(currentContent.content_data);
            } catch (e) {
                console.error('Invalid PPT data');
                loadMessage();
                return;
            }

            // Use Google Docs viewer for better PPT support
            const viewerUrl = `https://docs.google.com/viewer?url=${encodeURIComponent(window.location.origin + '/' + pptData.file_path)}&embedded=true`;

            wrapper.innerHTML = `
                <div class="ppt-container">
                    <iframe src="${viewerUrl}" class="ppt-iframe" allowfullscreen></iframe>
                </div>
            `;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadMessage() {
            const wrapper = document.getElementById('contentWrapper');
            const messageType = currentContent.message_type || 'memo';
            const messageText = currentContent.content_data;

            const icons = {
                warning: '⚠️',
                caution: '⚡',
                memo: '📝',
                congratulation: '🎉'
            };

            wrapper.innerHTML = `
                <div class="message-container">
                    <div class="message-card ${messageType}">
                        <div class="message-icon">${icons[messageType] || '📝'}</div>
                        <div class="message-text">${escapeHtml(messageText)}</div>
                    </div>
                </div>
            `;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadNextContent() {
            if (currentContent.next_content_id) {
                fetch(`get_content.php?id=${currentContent.next_content_id}`)
                    .then(response => response.json())
                    .then(data => {
                        currentContent = data.content;
                        currentSlides = data.slides || [];
                        loadContent();
                    })
                    .catch(error => {
                        console.error('Error loading next content:', error);
                        loadDefaultContent();
                    });
            } else {
                loadDefaultContent();
            }
        }

        function loadDefaultContent() {
            fetch(`get_content.php?mode=${currentMode}&default=true`)
                .then(response => response.json())
                .then(data => {
                    currentContent = data.content;
                    currentSlides = data.slides || [];
                    loadContent();
                })
                .catch(error => console.error('Error loading default content:', error));
        }

        function extractYouTubeId(url) {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : url;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function startRealTimeUpdates() {
            // Use SSE only (more efficient)
            if (eventSource) {
                eventSource.close();
            }

            eventSource = new EventSource('sse_updates.php');

            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    if (data.mode === currentMode) {
                        fetch(`get_content.php?mode=${currentMode}&t=${Date.now()}`)
                            .then(response => response.json())
                            .then(data => {
                                currentContent = data.content;
                                currentSlides = data.slides || [];
                                loadContent();
                            });
                    }
                } catch (e) {
                    console.error('SSE error:', e);
                }
            };

            eventSource.onerror = function() {
                console.log('SSE connection lost, reconnecting in 5 seconds...');
                eventSource.close();
                setTimeout(startRealTimeUpdates, 5000);
            };
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadContent();
            startRealTimeUpdates();

            // Nav dropdown functionality
            const navDropdown = document.getElementById('navDropdown');
            let hoverTimer;

            navDropdown.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimer);
                this.style.transform = 'translateX(0)';
            });

            navDropdown.addEventListener('mouseleave', function() {
                hoverTimer = setTimeout(() => {
                    if (!this.classList.contains('active')) {
                        this.style.transform = 'translateX(-98%)';
                    }
                }, 1000);
            });

            navDropdown.addEventListener('click', function(e) {
                if (!e.target.classList.contains('mode-option')) {
                    this.classList.toggle('active');
                    this.style.transform = this.classList.contains('active') ? 'translateX(0)' : 'translateX(-98%)';
                }
            });
        });
    </script>
</body>

</html>