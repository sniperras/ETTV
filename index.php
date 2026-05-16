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

// Handle API actions directly in index.php
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_content') {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
        $stmt->execute([$id]);
        $content = $stmt->fetch();

        if ($content) {
            $slides = [];
            if ($content['content_type'] === 'slideshow') {
                $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
                $stmt2->execute([$content['id']]);
                $slides = $stmt2->fetchAll();
            }
            echo json_encode(['success' => true, 'content' => $content, 'slides' => $slides]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Content not found']);
        }
    } elseif (isset($_GET['mode'])) {
        $mode = $_GET['mode'];
        $stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY COALESCE(display_order, 999999) ASC LIMIT 1");
        $stmt->execute([$mode]);
        $content = $stmt->fetch();

        if ($content) {
            $slides = [];
            if ($content['content_type'] === 'slideshow') {
                $stmt2 = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
                $stmt2->execute([$content['id']]);
                $slides = $stmt2->fetchAll();
            }
            echo json_encode(['success' => true, 'content' => $content, 'slides' => $slides]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No content']);
        }
    }
    exit();
}

if ($action === 'check_version') {
    header('Content-Type: application/json');
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'lmt';
    $current_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

    $stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
    $stmt->execute([$mode]);
    $result = $stmt->fetch();
    $latest_version = $result ? $result['version'] : 1;

    echo json_encode(['has_update' => $latest_version > $current_version, 'new_version' => $latest_version]);
    exit();
}

// Get current active content (first in the chain)
$stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY COALESCE(display_order, 999999) ASC LIMIT 1");
$stmt->execute([$current_mode]);
$current_content = $stmt->fetch();

// Parse content data
$layout_data = null;
$slides = [];

if ($current_content) {
    if ($current_content['content_type'] === 'slideshow') {
        $parsed = json_decode($current_content['content_data'], true);
        if ($parsed && isset($parsed['type']) && $parsed['type'] !== 'slideshow') {
            $layout_data = $parsed;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
            $stmt->execute([$current_content['id']]);
            $slides = $stmt->fetchAll();
        }
    }
}

// If no content, use default
if (!$current_content) {
    $current_content = [
        'id' => null,
        'content_type' => 'message',
        'content_data' => 'Welcome to LMT TV Display',
        'message_type' => 'memo',
        'display_duration' => 300,
        'loop_count' => 1,
        'next_content_id' => null
    ];
}

$current_version = 1;
$stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
$stmt->execute([$current_mode]);
$version = $stmt->fetch();
if ($version) $current_version = $version['version'];
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
            font-family: 'Segoe UI', sans-serif;
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
            overflow: hidden;
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
            top: 0;
            left: 0;
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
            background: #000;
        }

        .pdf-container {
            width: 100%;
            height: 100%;
            background: #000;
            position: relative;
        }

        .pdf-iframe {
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

        /* Fallback unmute button */
        .unmute-btn {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff4444;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2000;
            animation: pulse 1s infinite;
            box-shadow: 0 0 20px rgba(255, 68, 68, 0.5);
        }

        @keyframes pulse {
            0% {
                transform: translateX(-50%) scale(1);
                opacity: 1;
            }

            50% {
                transform: translateX(-50%) scale(1.05);
                opacity: 0.9;
            }

            100% {
                transform: translateX(-50%) scale(1);
                opacity: 1;
            }
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
        let currentLayoutData = <?php echo json_encode($layout_data); ?>;
        let currentSlides = <?php echo json_encode($slides); ?>;
        let currentVersion = <?php echo $current_version; ?>;
        let currentTimeouts = [];
        let pollingInterval = null;
        let currentYouTubePlayer = null;
        let unmuteButton = null;

        // Global flags for YouTube unmute detection
        window._unmuteDone = false;
        window._unmuteCheckTimer = null;

        function clearAllTimeouts() {
            currentTimeouts.forEach(timeout => clearTimeout(timeout));
            currentTimeouts = [];

            // Reset YouTube unmute flags
            window._unmuteDone = false;
            if (window._unmuteCheckTimer) {
                clearTimeout(window._unmuteCheckTimer);
                window._unmuteCheckTimer = null;
            }

            if (currentYouTubePlayer && typeof currentYouTubePlayer.destroy === 'function') {
                try {
                    currentYouTubePlayer.destroy();
                } catch (e) {}
                currentYouTubePlayer = null;
            }
            if (unmuteButton) {
                unmuteButton.remove();
                unmuteButton = null;
            }
        }

        function showUnmuteButton(player) {
            if (unmuteButton) return;
            unmuteButton = document.createElement('button');
            unmuteButton.className = 'unmute-btn';
            unmuteButton.innerHTML = '🔊 Tap to Enable Sound';
            unmuteButton.onclick = () => {
                try {
                    player.unMute();
                    player.setVolume(100);
                    player.playVideo();
                    unmuteButton.remove();
                    unmuteButton = null;
                    console.log('User manually unmuted');
                } catch (e) {
                    console.error('Manual unmute failed:', e);
                }
            };
            document.body.appendChild(unmuteButton);
        }

        function updateCurrentContent(data) {
            currentContent = data.content;
            currentSlides = data.slides || [];
            currentLayoutData = null;

            if (currentContent.content_type === 'slideshow' && currentContent.content_data) {
                try {
                    const parsed = JSON.parse(currentContent.content_data);
                    if (parsed.type && parsed.images && parsed.type !== 'slideshow') {
                        currentLayoutData = parsed;
                        currentSlides = [];
                    }
                } catch (e) {}
            }
        }

        function switchMode(mode) {
            if (mode === currentMode) return;
            currentMode = mode;
            document.getElementById('modeIndicator').innerHTML = `Current: ${mode.toUpperCase()}`;

            fetch(`?action=get_content&mode=${mode}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.content) {
                        updateCurrentContent(data);
                        loadContent();
                    }
                })
                .catch(error => console.error('Error:', error));

            document.getElementById('navDropdown').classList.remove('active');
        }

        function loadContent() {
            const wrapper = document.getElementById('contentWrapper');
            clearAllTimeouts();

            console.log('Loading content type:', currentContent.content_type);

            if (!currentContent || !currentContent.content_type) {
                console.error('Invalid content structure');
                return;
            }

            if (currentContent.content_type === 'slideshow') {
                if (currentLayoutData && currentLayoutData.type && currentLayoutData.images) {
                    loadMultiImageLayout(currentLayoutData);
                } else if (currentSlides && currentSlides.length > 0) {
                    loadSlideshow();
                } else {
                    try {
                        const parsed = JSON.parse(currentContent.content_data);
                        if (parsed.images && parsed.images.length > 0) {
                            if (parsed.type && parsed.type !== 'slideshow') {
                                loadMultiImageLayout(parsed);
                            } else {
                                currentSlides = parsed.images;
                                loadSlideshow();
                            }
                            return;
                        }
                    } catch (e) {}
                    loadMessage();
                }
            } else if (currentContent.content_type === 'youtube') {
                loadYouTube();
            } else if (currentContent.content_type === 'message') {
                loadMessage();
            } else if (currentContent.content_type === 'ppt') {
                loadPDF();
            } else {
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
                let imagePath = slide.image_path || slide.path;
                if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                    imagePath = '/' + imagePath;
                }
                imagePath = imagePath.replace('uploads/uploads/', 'uploads/');
                html += `<img src="${imagePath}" class="slide-image" data-index="${index}" style="opacity: ${index === 0 ? 1 : 0};" onerror="this.style.display='none'">`;
            });
            html += '</div>';
            wrapper.innerHTML = html;

            let currentIndex = 0;
            const slides = document.querySelectorAll('.slide-image');

            if (slides.length === 0) {
                loadMessage();
                return;
            }

            function showNextSlide() {
                if (!slides.length) return;
                slides[currentIndex].style.opacity = '0';
                currentIndex = (currentIndex + 1) % slides.length;
                slides[currentIndex].style.opacity = '1';
                const duration = (currentSlides[currentIndex]?.duration || 10) * 1000;
                const timeoutId = setTimeout(showNextSlide, duration);
                currentTimeouts.push(timeoutId);
            }

            if (slides.length > 1) {
                const firstDuration = (currentSlides[0]?.duration || 10) * 1000;
                const timeoutId = setTimeout(showNextSlide, firstDuration);
                currentTimeouts.push(timeoutId);
            }

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const expiryTimeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(expiryTimeoutId);
            }
        }

        function loadMultiImageLayout(layoutData) {
            const wrapper = document.getElementById('contentWrapper');
            const layoutType = layoutData.type;
            const images = layoutData.images || [];

            if (images.length === 0) {
                loadMessage();
                return;
            }

            let html = '';
            const gap = 10;
            const padding = 10;

            if (layoutType === '4-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else {
                const columns = layoutType === '2-image' ? '1fr 1fr' : '1fr 1fr 1fr';
                html = `<div style="display: grid; grid-template-columns: ${columns}; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            }

            images.forEach(image => {
                let imagePath = image.path;
                if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                    imagePath = '/' + imagePath;
                }
                imagePath = imagePath.replace('uploads/uploads/', 'uploads/');
                html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; overflow: hidden;">
                            <img src="${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'">
                        </div>`;
            });
            html += `</div>`;
            wrapper.innerHTML = html;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            const videoId = extractYouTubeId(currentContent.content_data);
            const containerId = 'yt-player-' + Date.now();

            wrapper.innerHTML = `<div id="${containerId}" style="width:100%;height:100%;background:#000;"></div>`;

            // Function to create player once API is ready
            const createPlayer = () => {
                if (currentYouTubePlayer) {
                    try {
                        currentYouTubePlayer.destroy();
                    } catch (e) {}
                }

                currentYouTubePlayer = new YT.Player(containerId, {
                    height: '100%',
                    width: '100%',
                    videoId: videoId,
                    playerVars: {
                        'autoplay': 1,
                        'mute': 1,
                        'controls': 0,
                        'rel': 0,
                        'modestbranding': 1,
                        'iv_load_policy': 3,
                        'enablejsapi': 1,
                        'playsinline': 1
                    },
                    events: {
                        'onReady': function(event) {
                            console.log('YouTube player ready');
                            event.target.mute();
                            event.target.playVideo();
                            // Don't attempt unmute here - wait for onStateChange
                        },
                        'onStateChange': function(event) {
                            if (event.data === YT.PlayerState.PLAYING) {
                                // Only attempt unmute once, when video confirms it's playing
                                if (!window._unmuteDone) {
                                    window._unmuteDone = true;
                                    console.log('Video playing, attempting unmute...');
                                    event.target.unMute();
                                    event.target.setVolume(100);

                                    // Set timer to detect if browser forces pause after unmute
                                    window._unmuteCheckTimer = setTimeout(function() {
                                        // If we reach here without being paused, unmute worked
                                        console.log('Unmute appears successful - video still playing');
                                        window._unmuteCheckTimer = null;
                                    }, 1000);
                                }
                            }

                            if (event.data === YT.PlayerState.PAUSED) {
                                // Check if this pause happened RIGHT AFTER our unmute attempt
                                if (window._unmuteDone && window._unmuteCheckTimer) {
                                    clearTimeout(window._unmuteCheckTimer);
                                    window._unmuteCheckTimer = null;
                                    // Browser forced pause = unmute was blocked
                                    console.log('Browser blocked unmute - showing button');
                                    window._unmuteDone = false;
                                    // Restart muted silently
                                    event.target.mute();
                                    event.target.playVideo();
                                    // Show the tap-to-unmute button
                                    showUnmuteButton(event.target);
                                }
                                // Other pauses (e.g. from clearAllTimeouts) - do nothing
                            }

                            if (event.data === YT.PlayerState.ENDED) {
                                console.log('Video ended, moving to next content');
                                loadNextContent();
                            }
                        },
                        'onError': function(event) {
                            console.error('YouTube error:', event.data);
                        }
                    }
                });
            };

            // Load YouTube API if not already loaded
            if (typeof YT !== 'undefined' && YT.Player) {
                createPlayer();
            } else {
                window._pendingYouTubeCreate = createPlayer;
                if (!document.getElementById('yt-api-script')) {
                    const tag = document.createElement('script');
                    tag.id = 'yt-api-script';
                    tag.src = 'https://www.youtube.com/iframe_api';
                    document.head.appendChild(tag);
                }
            }

            // Fallback timer to move to next content
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    console.log('Video duration expired (fallback), loading next content');
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadPDF() {
            const wrapper = document.getElementById('contentWrapper');
            let pdfData;

            try {
                pdfData = JSON.parse(currentContent.content_data);
            } catch (e) {
                pdfData = {
                    file_path: currentContent.content_data
                };
            }

            let filePath = pdfData.file_path;
            if (!filePath.startsWith('/') && !filePath.startsWith('http')) {
                filePath = '/' + filePath;
            }
            filePath = filePath.replace('uploads/uploads/', 'uploads/');

            const viewerUrl = `https://docs.google.com/viewer?url=${encodeURIComponent(window.location.origin + filePath)}&embedded=true`;
            wrapper.innerHTML = `<div class="pdf-container"><iframe src="${viewerUrl}" class="pdf-iframe" allowfullscreen></iframe></div>`;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadMessage() {
            const wrapper = document.getElementById('contentWrapper');
            const messageType = currentContent.message_type || 'memo';
            const messageText = currentContent.content_data || 'Welcome to ET TV Display';
            const icons = {
                warning: '⚠️',
                caution: '⚡',
                memo: '📝',
                congratulation: '🎉'
            };

            wrapper.innerHTML = `<div class="message-container"><div class="message-card ${messageType}"><div class="message-icon">${icons[messageType] || '📝'}</div><div class="message-text">${escapeHtml(messageText)}</div></div></div>`;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadNextContent() {
            const nextId = parseInt(currentContent.next_content_id);
            console.log('Loading next content, next_content_id:', currentContent.next_content_id);

            if (!isNaN(nextId) && nextId > 0) {
                fetch(`?action=get_content&id=${nextId}&t=${Date.now()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.content) {
                            updateCurrentContent(data);
                            setTimeout(() => loadContent(), 200);
                        } else {
                            console.log('Next content not found, waiting for admin');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading next content:', error);
                    });
            } else {
                console.log('Content chain ended, waiting for new content from admin');
            }
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

        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);

            pollingInterval = setInterval(function() {
                fetch(`?action=check_version&mode=${currentMode}&version=${currentVersion}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_update) {
                            console.log('Update detected, refreshing content');
                            currentVersion = data.new_version;
                            fetch(`?action=get_content&mode=${currentMode}&t=${Date.now()}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.content) {
                                        updateCurrentContent(data);
                                        loadContent();
                                    }
                                })
                                .catch(error => console.error('Fetch error:', error));
                        }
                    })
                    .catch(error => console.error('Polling error:', error));
            }, 5000);
        }

        // YouTube API callback
        window.onYouTubeIframeAPIReady = function() {
            console.log('YouTube API ready');
            if (window._pendingYouTubeCreate) {
                const create = window._pendingYouTubeCreate;
                window._pendingYouTubeCreate = null;
                create();
            }
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadContent();
            startPolling();

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