<?php
require_once 'config/db.php';

// Handle version check API request (MUST be at the very top before ANY output)
if (isset($_GET['action']) && $_GET['action'] === 'check_version') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'lmt';
    $current_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

    $stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
    $stmt->execute([$mode]);
    $version = $stmt->fetch();
    $server_version = $version ? (int)$version['version'] : 1;

    // Also get the first content ID to redirect to base URL
    $stmt2 = $pdo->prepare("SELECT id FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY COALESCE(display_order, 999999) ASC LIMIT 1");
    $stmt2->execute([$mode]);
    $first_content = $stmt2->fetch();
    $first_content_id = $first_content ? $first_content['id'] : null;

    echo json_encode([
        'has_update' => ($server_version > $current_version),
        'server_version' => $server_version,
        'first_content_id' => $first_content_id
    ]);
    exit();
}

// Get current display mode
$current_mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_SESSION['display_mode']) ? $_SESSION['display_mode'] : 'lmt');
if (isset($_GET['mode'])) {
    $_SESSION['display_mode'] = $current_mode;
}

// Validate mode
if (!in_array($current_mode, ['lmt', 'bmt'])) {
    $current_mode = 'lmt';
}

// Handle content by ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND admin_role = ? AND is_active = 1");
    $stmt->execute([$id, $current_mode]);
    $current_content = $stmt->fetch();

    if (!$current_content) {
        header('Location: ?mode=' . $current_mode);
        exit();
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM content WHERE admin_role = ? AND is_active = 1 ORDER BY COALESCE(display_order, 999999) ASC LIMIT 1");
    $stmt->execute([$current_mode]);
    $current_content = $stmt->fetch();
}

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

if (!$current_content) {
    $current_content = [
        'id' => null,
        'content_type' => 'message',
        'content_data' => 'Welcome to ET TV Display',
        'message_type' => 'memo',
        'description' => '',
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
    <link rel="icon" type="image/png" href="/img/ethiopian_logo.ico">
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
            width: 280px;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(20px);
            transform: translateX(-96%);
            transition: transform 0.4s ease;
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-dropdown:hover,
        .nav-dropdown.active {
            transform: translateX(0);
        }

        .nav-content {
            padding: 30px 20px;
            color: white;
        }

        .nav-content h3 {
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 500;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }

        .mode-option {
            display: block;
            width: 100%;
            padding: 18px 20px;
            margin: 12px 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 20px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .mode-option:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateX(5px);
            border-color: transparent;
        }

        .display-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow: hidden;
            position: relative;
            background: #000;
        }

        .description-bar {
            position: relative;
            width: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            color: white;
            text-align: center;
            padding: 18px 24px;
            font-size: 20px;
            font-weight: 500;
            z-index: 1001;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.4s ease, visibility 0.4s ease;
            flex-shrink: 0;
        }

        .description-bar.visible {
            opacity: 1;
            visibility: visible;
        }

        .content-wrapper {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            min-height: 0;
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
            transition: opacity 0.8s ease-in-out;
        }

        .slide-image.active {
            opacity: 1;
        }

        .youtube-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #000;
        }

        .youtube-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .local-video-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .local-video-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .audio-container {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .audio-waveform {
            width: 90%;
            max-width: 1200px;
            height: 300px;
            margin: 20px auto;
            position: relative;
        }

        .audio-waveform canvas {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .audio-info {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .audio-title {
            font-size: 48px;
            font-weight: 600;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .audio-wave-bar {
            width: 10px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 5px;
            animation: wave 1s ease-in-out infinite;
        }

        .audio-wave-bar:nth-child(1) {
            animation-delay: 0s;
            height: 30px;
        }

        .audio-wave-bar:nth-child(2) {
            animation-delay: 0.1s;
            height: 50px;
        }

        .audio-wave-bar:nth-child(3) {
            animation-delay: 0.2s;
            height: 70px;
        }

        .audio-wave-bar:nth-child(4) {
            animation-delay: 0.3s;
            height: 60px;
        }

        .audio-wave-bar:nth-child(5) {
            animation-delay: 0.4s;
            height: 80px;
        }

        .audio-wave-bar:nth-child(6) {
            animation-delay: 0.5s;
            height: 55px;
        }

        .audio-wave-bar:nth-child(7) {
            animation-delay: 0.6s;
            height: 45px;
        }

        .audio-wave-bar:nth-child(8) {
            animation-delay: 0.7s;
            height: 65px;
        }

        .audio-wave-bar:nth-child(9) {
            animation-delay: 0.8s;
            height: 35px;
        }

        .audio-wave-bar:nth-child(10) {
            animation-delay: 0.9s;
            height: 40px;
        }

        .audio-wave-bar:nth-child(11) {
            animation-delay: 1.0s;
            height: 50px;
        }

        .audio-wave-bar:nth-child(12) {
            animation-delay: 1.1s;
            height: 25px;
        }

        @keyframes wave {

            0%,
            100% {
                transform: scaleY(1);
            }

            50% {
                transform: scaleY(0.5);
            }
        }

        .pdf-container {
            width: 100%;
            height: 100%;
            background: #000;
            position: relative;
            overflow: hidden;
        }

        .pdf-viewer {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }

        .pdf-viewer.two-pages {
            flex-direction: row;
            gap: 30px;
            padding: 40px;
        }

        .pdf-viewer.single-page {
            flex-direction: column;
        }

        .pdf-page-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            height: 100%;
            min-height: 0;
        }

        .pdf-page-wrapper.single {
            max-width: 90%;
            max-height: 90%;
            width: auto;
            height: auto;
            flex: none;
        }

        .pdf-page-wrapper canvas {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .pdf-page-number {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            font-size: 16px;
            z-index: 10;
            font-weight: 500;
            backdrop-filter: blur(10px);
            pointer-events: none;
        }

        .pdf-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 20px;
            text-align: center;
        }

        .website-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #fff;
        }

        .website-iframe {
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
            padding: 60px;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .message-card {
            max-width: 85%;
            padding: 80px;
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .warning {
            background: #dc3545;
            border-left: 8px solid #ff0000;
        }

        .caution {
            background: #fd7e14;
            border-left: 8px solid #ff9800;
        }

        .memo {
            background: #6c5ce7;
            border-left: 8px solid #2196f3;
        }

        .congratulation {
            background: #28a745;
            border-left: 8px solid #4caf50;
            color: #333;
        }

        .message-icon {
            font-size: 100px;
            margin-bottom: 30px;
        }

        .message-text {
            font-size: 52px;
            line-height: 1.4;
            font-weight: 600;
        }

        .warning .message-text,
        .caution .message-text,
        .memo .message-text {
            color: #fff;
        }

        .congratulation .message-text {
            color: #fff;
        }

        .mode-indicator {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(10px);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            z-index: 999;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 1024px) {
            .message-text {
                font-size: 36px;
            }

            .message-card {
                padding: 50px;
            }

            .message-icon {
                font-size: 70px;
            }

            .description-bar {
                font-size: 16px;
                padding: 12px 20px;
            }

            .audio-title {
                font-size: 32px;
            }

            .audio-waveform {
                height: 200px;
            }
        }

        @media (max-width: 768px) {
            .message-text {
                font-size: 24px;
            }

            .message-card {
                padding: 35px;
            }

            .message-icon {
                font-size: 50px;
            }

            .nav-dropdown {
                width: 85%;
                transform: translateX(-92%);
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.PDFJS = pdfjsLib.PDFJS || {};
            pdfjsLib.PDFJS.verbosity = 0;
        }
    </script>
</head>

<body>
    <div class="nav-dropdown" id="navDropdown">
        <div class="nav-content">
            <h3>SELECT DISPLAY</h3>
            <button class="mode-option" onclick="switchMode('lmt')">🟦 LMT Display</button>
            <button class="mode-option" onclick="switchMode('bmt')">🟪 BMT Display</button>
        </div>
    </div>

    <div id="descriptionBar" class="description-bar"></div>

    <div class="display-container">
        <div class="content-wrapper" id="contentWrapper"></div>
    </div>

    <div class="mode-indicator" id="modeIndicator">
        CURRENT: <?php echo strtoupper($current_mode); ?>
    </div>

    <script>
        let currentMode = '<?php echo $current_mode; ?>';
        let currentContent = <?php echo json_encode($current_content); ?>;
        let currentLayoutData = <?php echo json_encode($layout_data); ?>;
        let currentSlides = <?php echo json_encode($slides); ?>;
        let currentVersion = <?php echo $current_version; ?>;
        let currentTimeouts = [];
        let pollingInterval = null;
        let localVideoUnlockAttempts = 0;

        let pdfDoc = null;
        let currentPageIndex = 0;
        let totalPages = 0;
        let pdfRotationInterval = null;
        let currentAudio = null;
        let animationId = null;

        function clearAllTimeouts() {
            currentTimeouts.forEach(timeout => clearTimeout(timeout));
            currentTimeouts = [];
            if (window.pairTimeoutIds) {
                window.pairTimeoutIds.forEach(id => clearTimeout(id));
                window.pairTimeoutIds = [];
            }
            if (pdfRotationInterval) {
                clearInterval(pdfRotationInterval);
                pdfRotationInterval = null;
            }
            if (currentAudio) {
                currentAudio.pause();
                currentAudio = null;
            }
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
            // Stop website scrolling if active
            if (window.websiteScrollInterval) {
                clearInterval(window.websiteScrollInterval);
                window.websiteScrollInterval = null;
            }
        }

        function showDescription(description) {
            const descBar = document.getElementById('descriptionBar');
            if (description && description.trim() !== '') {
                descBar.innerHTML = description;
                descBar.classList.add('visible');
                // REMOVED auto-hide - description stays permanently
            } else {
                descBar.classList.remove('visible');
                descBar.innerHTML = '';
            }
        }

        function switchMode(mode) {
            if (mode === currentMode) return;
            currentMode = mode;
            window.location.href = '?mode=' + mode;
        }

        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);

            pollingInterval = setInterval(function() {
                const url = window.location.pathname + '?action=check_version&mode=' + currentMode + '&version=' + currentVersion;
                console.log('Polling version check:', url);

                fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Version check response:', data);
                        if (data && data.has_update) {
                            console.log('Update detected! Refreshing to base URL');
                            // Refresh to base URL to start from first content
                            window.location.href = '/?mode=' + currentMode;
                        }
                    })
                    .catch(error => {
                        console.log('Polling error:', error);
                    });
            }, 3000);
        }

        function loadWebsite() {
            const wrapper = document.getElementById('contentWrapper');
            let websiteData;
            let isScrolling = true;
            let scrollSpeed = 2; // Adjust for smoother/faster scrolling

            try {
                websiteData = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
            } catch (e) {
                websiteData = {
                    url: currentContent.content_data,
                    title: 'Website'
                };
            }

            const websiteUrl = websiteData.url;
            const websiteTitle = websiteData.title || currentContent.description || 'Website';

            // Use our own PHP proxy
            const proxyUrl = '/proxy.php?url=' + encodeURIComponent(websiteUrl);

            wrapper.innerHTML = `
    <div class="website-container" style="width:100%;height:100%;background:#fff;display:flex;flex-direction:column;">
        <div style="background:rgba(0,0,0,0.8);color:white;padding:8px 15px;font-size:12px;text-align:center;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
            <span>🌐 ${escapeHtml(websiteTitle)}</span>
            <div style="display:flex;gap:10px;">
                <button id="scrollToggleBtn" style="background:#667eea;color:white;border:none;padding:4px 12px;border-radius:15px;cursor:pointer;font-size:11px;">⏸ Pause Scroll</button>
                <button id="scrollResetBtn" style="background:#28a745;color:white;border:none;padding:4px 12px;border-radius:15px;cursor:pointer;font-size:11px;">🔄 Reset</button>
            </div>
        </div>
        <div style="flex:1;position:relative;overflow:hidden;">
            <iframe 
                id="websiteIframe"
                src="${proxyUrl}"
                style="width:100%;height:100%;border:none;"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                allowfullscreen
                title="${escapeHtml(websiteTitle)}">
            </iframe>
        </div>
        <div class="scroll-footer" style="background:rgba(0,0,0,0.6);color:white;padding:4px 10px;font-size:10px;text-align:center;flex-shrink:0;">
            🔄 Auto-scrolling • Scrolls down slowly, returns quickly
        </div>
    </div>
`;

            initWebsiteScrolling(wrapper);

            // Set overall duration timeout to move to next content
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function initWebsiteScrolling(wrapper) {
            const iframe = document.getElementById('websiteIframe');
            const toggleBtn = document.getElementById('scrollToggleBtn');
            const resetBtn = document.getElementById('scrollResetBtn');

            let scrollDirection = 1; // 1 = down, -1 = up
            let currentPosition = 0;
            let scrollAnimationId = null;
            let scrollTimeout = null;
            let scrollAttempts = 0;
            let canScroll = true;
            let isScrolling = true;
            let scrollSpeed = 1; // Normal scroll speed
            let fastScrollSpeed = 20; // Fast speed when returning to top

            function startScrolling() {
                if (scrollAnimationId) return;
                if (!canScroll) return;

                function smoothScroll() {
                    if (!isScrolling) return;

                    try {
                        let iframeDoc = null;
                        try {
                            iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        } catch (e) {
                            if (scrollAttempts < 30) {
                                scrollAttempts++;
                                scrollAnimationId = requestAnimationFrame(smoothScroll);
                            } else {
                                canScroll = false;
                                if (toggleBtn) toggleBtn.style.display = 'none';
                                if (resetBtn) resetBtn.style.display = 'none';
                                if (scrollAnimationId) cancelAnimationFrame(scrollAnimationId);
                                const footer = wrapper.querySelector('.scroll-footer');
                                if (footer) {
                                    footer.innerHTML = '⚠️ Auto-scroll not available for this website';
                                    footer.style.background = 'rgba(220,53,69,0.8)';
                                }
                            }
                            return;
                        }

                        if (iframeDoc && iframeDoc.body) {
                            const maxScroll = iframeDoc.body.scrollHeight - iframeDoc.documentElement.clientHeight;

                            if (maxScroll <= 0) {
                                if (toggleBtn) toggleBtn.style.display = 'none';
                                if (resetBtn) resetBtn.style.display = 'none';
                                const footer = wrapper.querySelector('.scroll-footer');
                                if (footer) {
                                    footer.innerHTML = '📄 Content fits on screen - no scrolling needed';
                                    footer.style.background = 'rgba(0,0,0,0.6)';
                                }
                                return;
                            }

                            // Use different speeds based on direction
                            const currentSpeed = (scrollDirection === 1) ? scrollSpeed : fastScrollSpeed;

                            if (scrollDirection === 1) {
                                // Scrolling down (slow)
                                if (currentPosition < maxScroll) {
                                    currentPosition += currentSpeed;
                                    if (currentPosition >= maxScroll) {
                                        currentPosition = maxScroll;
                                        scrollDirection = -1; // Switch to up direction
                                        clearTimeout(scrollTimeout);
                                        // Small pause at bottom before going up fast
                                        scrollTimeout = setTimeout(() => {
                                            scrollAnimationId = requestAnimationFrame(smoothScroll);
                                        }, 800);
                                        return;
                                    }
                                } else {
                                    scrollDirection = -1;
                                    clearTimeout(scrollTimeout);
                                    scrollTimeout = setTimeout(() => {
                                        scrollAnimationId = requestAnimationFrame(smoothScroll);
                                    }, 500);
                                    return;
                                }
                            } else {
                                // Scrolling up (fast)
                                if (currentPosition > 0) {
                                    currentPosition -= currentSpeed;
                                    if (currentPosition <= 0) {
                                        currentPosition = 0;
                                        scrollDirection = 1; // Switch back to down direction
                                        clearTimeout(scrollTimeout);
                                        // Pause at top before scrolling down again
                                        scrollTimeout = setTimeout(() => {
                                            scrollAnimationId = requestAnimationFrame(smoothScroll);
                                        }, 1000);
                                        return;
                                    }
                                } else {
                                    scrollDirection = 1;
                                    clearTimeout(scrollTimeout);
                                    scrollTimeout = setTimeout(() => {
                                        scrollAnimationId = requestAnimationFrame(smoothScroll);
                                    }, 500);
                                    return;
                                }
                            }

                            // Apply smooth scrolling
                            iframeDoc.body.style.scrollBehavior = 'smooth';
                            iframeDoc.documentElement.style.scrollBehavior = 'smooth';
                            iframe.contentWindow.scrollTo({
                                top: currentPosition,
                                behavior: 'smooth'
                            });

                            // Update footer status
                            const footer = wrapper.querySelector('.scroll-footer');
                            if (footer && Math.random() < 0.05) { // Update occasionally
                                if (scrollDirection === 1) {
                                    footer.innerHTML = '🔄 Scrolling down slowly...';
                                } else {
                                    footer.innerHTML = '⚡ Scrolling up quickly...';
                                }
                            }

                            scrollAnimationId = requestAnimationFrame(smoothScroll);
                        } else {
                            scrollAnimationId = requestAnimationFrame(smoothScroll);
                        }
                    } catch (e) {
                        if (scrollAttempts < 30) {
                            scrollAttempts++;
                            scrollAnimationId = requestAnimationFrame(smoothScroll);
                        } else {
                            canScroll = false;
                            if (toggleBtn) toggleBtn.style.display = 'none';
                            if (resetBtn) resetBtn.style.display = 'none';
                            if (scrollAnimationId) cancelAnimationFrame(scrollAnimationId);
                        }
                    }
                }

                scrollAnimationId = requestAnimationFrame(smoothScroll);
            }

            function stopScrolling() {
                if (scrollAnimationId) {
                    cancelAnimationFrame(scrollAnimationId);
                    scrollAnimationId = null;
                }
                if (scrollTimeout) {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = null;
                }
            }

            function resetToTop() {
                stopScrolling();
                currentPosition = 0;
                scrollDirection = 1; // Reset to down direction

                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc && iframeDoc.body) {
                        iframeDoc.body.style.scrollBehavior = 'auto';
                        iframe.contentWindow.scrollTo({
                            top: 0,
                            behavior: 'auto'
                        });
                    }
                } catch (e) {
                    console.log('Cannot reset scroll');
                }

                // Restart scrolling after reset
                if (isScrolling && canScroll) {
                    setTimeout(() => {
                        startScrolling();
                    }, 500);
                }
            }

            // Toggle scroll on/off
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    isScrolling = !isScrolling;
                    if (isScrolling) {
                        startScrolling();
                        toggleBtn.textContent = '⏸ Pause Scroll';
                        toggleBtn.style.background = '#667eea';
                    } else {
                        stopScrolling();
                        toggleBtn.textContent = '▶️ Start Scroll';
                        toggleBtn.style.background = '#28a745';
                    }
                });
            }

            // Reset button
            if (resetBtn) {
                resetBtn.addEventListener('click', resetToTop);
            }

            // Start scrolling when iframe loads
            if (iframe) {
                iframe.addEventListener('load', () => {
                    console.log('Iframe loaded, starting auto-scroll...');
                    setTimeout(() => {
                        if (canScroll) {
                            startScrolling();
                            const footer = wrapper.querySelector('.scroll-footer');
                            if (footer) {
                                footer.innerHTML = '🔄 Auto-scrolling active • Scrolls down slow, returns fast';
                                footer.style.background = 'rgba(0,0,0,0.6)';
                            }
                        }
                    }, 2000);
                });

                setTimeout(() => {
                    if (canScroll && !scrollAnimationId) {
                        startScrolling();
                    }
                }, 5000);
            }
        }

        function loadPPT() {
            const wrapper = document.getElementById('contentWrapper');
            let pptData;

            try {
                pptData = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
            } catch (e) {
                pptData = {
                    file_path: currentContent.content_data
                };
            }

            let filePath = pptData.file_path;
            if (!filePath) {
                console.error('No file path found in PPT data');
                showPPTError(wrapper, 'No file path specified');
                return;
            }

            // Clean up the file path
            filePath = filePath.replace(/^\/+/, '');
            filePath = filePath.replace(/\\/g, '/');

            // Ensure the path is correct
            if (!filePath.startsWith('uploads/')) {
                filePath = 'uploads/' + filePath;
            }

            // Fix any double slashes
            filePath = filePath.replace(/\/+/g, '/');

            const pptUrl = window.location.origin + '/' + filePath;
            const fileExt = filePath.split('.').pop().toLowerCase();
            const fileName = filePath.split('/').pop();

            console.log('Loading PPT from URL:', pptUrl, 'Extension:', fileExt);

            // First check if the file exists
            fetch(pptUrl, {
                    method: 'HEAD'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: File not found`);
                    }

                    // Use Microsoft Office Online Viewer for PPT/PPTX files
                    // This is the official Microsoft viewer that works great
                    const encodedUrl = encodeURIComponent(pptUrl);
                    const msViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodedUrl}`;

                    wrapper.innerHTML = `
                <div style="width:100%;height:100%;background:#f5f5f5;display:flex;flex-direction:column;">
                    <div style="flex:1;position:relative;">
                        <iframe src="${msViewerUrl}" 
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                allowfullscreen>
                        </iframe>
                    </div>
                    <div style="background:rgba(0,0,0,0.8);color:white;padding:8px 15px;font-size:12px;text-align:center;z-index:10;">
                        📊 ${escapeHtml(fileName)} | Microsoft Office Viewer
                    </div>
                </div>
            `;

                    if (currentContent.display_duration && currentContent.display_duration > 0) {
                        currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                    }
                })
                .catch(error => {
                    console.error('PPT file check error:', error);
                    // Fallback to Google Docs Viewer if Microsoft fails
                    const encodedUrl = encodeURIComponent(pptUrl);
                    const googleViewerUrl = `https://docs.google.com/gview?url=${encodedUrl}&embedded=true`;

                    wrapper.innerHTML = `
                <div style="width:100%;height:100%;background:#f5f5f5;display:flex;flex-direction:column;">
                    <div style="flex:1;position:relative;">
                        <iframe src="${googleViewerUrl}" 
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                allowfullscreen>
                        </iframe>
                    </div>
                    <div style="background:rgba(0,0,0,0.8);color:white;padding:8px 15px;font-size:12px;text-align:center;z-index:10;">
                        📊 ${escapeHtml(fileName)} | Google Docs Viewer
                    </div>
                </div>
            `;

                    if (currentContent.display_duration && currentContent.display_duration > 0) {
                        currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                    }
                });
        }

        function showPPTError(wrapper, errorMsg) {
            wrapper.innerHTML = `
        <div class="message-container">
            <div class="message-card memo">
                <div class="message-icon">📊</div>
                <div class="message-text">Unable to display PowerPoint</div>
                <div style="margin-top: 15px; font-size: 14px; color: #ff6b6b;">${escapeHtml(errorMsg)}</div>
                <div style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                    <div style="font-size: 14px; line-height: 1.6;">
                        💡 <strong>Tips:</strong><br>
                        • Convert PowerPoint to PDF for best compatibility<br>
                        • Or upload as PDF using the admin panel<br>
                        • PDF files display perfectly on all devices
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button onclick="window.location.href='/lmt/lmtadmin.php'" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 5px;">
                        📤 Upload PDF Instead
                    </button>
                </div>
                <div style="margin-top: 15px; font-size: 12px; opacity: 0.6;">
                    Moving to next content in ${Math.ceil(currentContent.display_duration / 10)} seconds...
                </div>
            </div>
        </div>
    `;

            if (currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function showPPTError(wrapper, errorMsg) {
            wrapper.innerHTML = `
        <div class="message-container">
            <div class="message-card memo">
                <div class="message-icon">📊</div>
                <div class="message-text">Error loading PowerPoint</div>
                <div style="margin-top: 15px; font-size: 14px; color: #ff6b6b;">${escapeHtml(errorMsg)}</div>
                <div style="margin-top: 10px; font-size: 12px; opacity: 0.7;">
                    💡 Tip: Convert your PowerPoint to PDF for better compatibility
                </div>
                <div style="margin-top: 20px;">
                    <button onclick="window.location.href='/lmt/lmtadmin.php'" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                        📤 Upload PDF Instead
                    </button>
                </div>
            </div>
        </div>
    `;
            if (currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function loadContent() {
            const wrapper = document.getElementById('contentWrapper');
            clearAllTimeouts();

            if (!currentContent || !currentContent.content_type) {
                wrapper.innerHTML = `<div class="message-container"><div class="message-card memo"><div class="message-icon">📺</div><div class="message-text">No content available for ${currentMode.toUpperCase()} mode.<br>Please check back later.</div></div></div>`;
                return;
            }

            // Show description for all content types (permanently)
            if (currentContent.description && currentContent.description.trim() !== '') {
                showDescription(currentContent.description);
            } else {
                showDescription('');
            }

            // Handle different content types
            switch (currentContent.content_type) {
                case 'slideshow':
                    if (currentLayoutData && currentLayoutData.type && currentLayoutData.type !== 'slideshow') {
                        if (currentLayoutData.type === '2-image' || currentLayoutData.type === '3-image' || currentLayoutData.type === '4-image') {
                            loadSlideshowPairs(currentLayoutData);
                        } else {
                            loadMultiImageLayout(currentLayoutData);
                        }
                        return;
                    }
                    if (currentSlides && currentSlides.length > 0) {
                        loadSlideshow();
                        return;
                    }
                    try {
                        const parsed = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
                        if (parsed && parsed.images && parsed.images.length > 0) {
                            if (parsed.type && (parsed.type === '2-image' || parsed.type === '3-image' || parsed.type === '4-image')) {
                                loadSlideshowPairs(parsed);
                            } else {
                                currentSlides = parsed.images;
                                loadSlideshow();
                            }
                            return;
                        }
                    } catch (e) {}
                    loadMessage();
                    break;

                case 'youtube':
                    loadYouTube();
                    break;

                case 'local_video':
                    loadLocalVideo();
                    break;

                case 'local_audio':
                    loadAudio();
                    break;

                case 'message':
                    loadMessage();
                    break;

                case 'ppt':
                    loadPPT();
                    break;

                case 'pdf':
                    loadPDF();
                    break;

                case 'website':
                    loadWebsite();
                    break;

                default:
                    loadMessage();
            }
        }

        function generateFakeWaveform() {
            const canvas = document.getElementById('audioWaveformCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            canvas.width = width;
            canvas.height = height;
            const barCount = 100;
            const barWidth = width / barCount;

            function draw() {
                ctx.clearRect(0, 0, width, height);
                for (let i = 0; i < barCount; i++) {
                    const barHeight = 30 + Math.random() * (height - 60);
                    const x = i * barWidth;
                    const y = (height - barHeight) / 2;
                    const gradient = ctx.createLinearGradient(x, y, x, y + barHeight);
                    gradient.addColorStop(0, '#667eea');
                    gradient.addColorStop(1, '#764ba2');
                    ctx.fillStyle = gradient;
                    ctx.fillRect(x, y, barWidth - 2, barHeight);
                }
                animationId = requestAnimationFrame(draw);
            }
            draw();
        }

        function loadAudio() {
            const wrapper = document.getElementById('contentWrapper');
            let audioData;
            try {
                audioData = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
            } catch (e) {
                audioData = {
                    file_path: currentContent.content_data
                };
            }
            let audioPath = audioData.file_path || audioData.path;
            if (!audioPath) {
                loadMessage();
                return;
            }
            audioPath = audioPath.replace(/^\/+/, '');
            if (!audioPath.startsWith('uploads/') && !audioPath.startsWith('audio/')) {
                audioPath = 'uploads/audio/' + audioPath.replace(/^uploads\/?/, '');
            }
            const audioTitle = audioData.title || 'Background Audio';
            const showWaveform = audioData.show_waveform !== undefined ? audioData.show_waveform : true;

            // No play button - just show waveform and title
            wrapper.innerHTML = `
        <div class="audio-container">
            <div class="audio-info"><div class="audio-title">🎵 ${escapeHtml(audioTitle)}</div></div>
            ${showWaveform ? `<div class="audio-waveform"><canvas id="audioWaveformCanvas"></canvas></div>` : ''}
           
        </div>
    `;

            const audio = new Audio('/' + audioPath);
            currentAudio = audio;
            audio.loop = true;
            audio.volume = 1.0;

            // Try multiple approaches to autoplay
            const attemptAutoplay = () => {
                audio.play().then(() => {
                    console.log('Audio playing automatically');
                }).catch(e => {
                    console.log('Autoplay prevented:', e);
                    // Try with muted first, then unmute
                    audio.muted = true;
                    audio.play().then(() => {
                        console.log('Audio playing muted, now unmuting');
                        audio.muted = false;
                    }).catch(err => {
                        console.log('Even muted autoplay failed:', err);
                        // Last resort - show minimal play hint (but TV users may not see it)
                        const hint = document.createElement('div');
                        hint.textContent = '🔊';
                        hint.style.cssText = 'position:fixed;bottom:20px;right:20px;background:rgba(0,0,0,0.5);color:white;padding:5px 10px;border-radius:20px;font-size:12px;z-index:9999;opacity:0.5';
                        document.body.appendChild(hint);
                        setTimeout(() => hint.remove(), 3000);
                    });
                });
            };

            // Wait for audio to be ready
            audio.addEventListener('canplay', attemptAutoplay);

            audio.addEventListener('error', function(e) {
                console.error('Audio error:', e);
                console.error('Audio error code:', audio.error ? audio.error.code : 'unknown');
                if (currentContent.display_duration && currentContent.display_duration > 0) {
                    const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                    currentTimeouts.push(timeoutId);
                }
            });

            audio.load();

            if (showWaveform) {
                generateFakeWaveform();
            }

            // Set timeout to move to next content after display_duration
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const overallTimeoutId = setTimeout(() => {
                    console.log('Audio duration expired, moving to next content');
                    if (currentAudio) {
                        currentAudio.pause();
                    }
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(overallTimeoutId);
            }
        }

        function loadNextContent() {
            const nextId = currentContent.next_content_id;
            if (nextId && !isNaN(parseInt(nextId)) && parseInt(nextId) > 0) {
                console.log('Moving to next content ID:', nextId);
                window.location.href = '?id=' + nextId + '&mode=' + currentMode;
            } else {
                console.log('End of content chain, restarting from beginning');
                // Go back to base URL to start from the first content
                window.location.href = '?mode=' + currentMode;
            }
        }

        function loadSlideshow() {
            const wrapper = document.getElementById('contentWrapper');
            if (!currentSlides || currentSlides.length === 0) {
                loadMessage();
                return;
            }
            if (currentSlides.length === 1) {
                let imagePath = currentSlides[0].image_path || currentSlides[0].path;
                if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) imagePath = '/' + imagePath;
                imagePath = imagePath.replace('uploads/uploads/', 'uploads/');
                wrapper.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;"><img src="${imagePath}" style="width:100%;height:100%;object-fit:contain;"></div>`;
                if (currentContent.display_duration && currentContent.display_duration > 0) {
                    currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                }
                return;
            }
            let html = '<div class="slideshow-container">';
            currentSlides.forEach((slide, index) => {
                let imagePath = slide.image_path || slide.path;
                if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) imagePath = '/' + imagePath;
                imagePath = imagePath.replace('uploads/uploads/', 'uploads/');
                html += `<img src="${imagePath}" class="slide-image" data-index="${index}" style="opacity: ${index === 0 ? 1 : 0};">`;
            });
            html += '</div>';
            wrapper.innerHTML = html;
            let currentIndex = 0;
            const slides = document.querySelectorAll('.slide-image');

            function showNextSlide() {
                if (!slides.length) return;
                slides[currentIndex].style.opacity = '0';
                currentIndex = (currentIndex + 1) % slides.length;
                slides[currentIndex].style.opacity = '1';
                const duration = (currentSlides[currentIndex]?.duration || 10) * 1000;
                currentTimeouts.push(setTimeout(showNextSlide, duration));
            }
            if (slides.length > 1) currentTimeouts.push(setTimeout(showNextSlide, (currentSlides[0]?.duration || 10) * 1000));
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function loadSlideshowPairs(layoutData) {
            const wrapper = document.getElementById('contentWrapper');
            const images = layoutData.images || [];
            const imagesPerView = layoutData.type === '2-image' ? 2 : (layoutData.type === '3-image' ? 3 : 4);
            if (images.length === 0) {
                loadMessage();
                return;
            }
            if (images.length === 1 && imagesPerView > 1) {
                let imagePath = images[0].path || images[0].image_path;
                if (imagePath) {
                    imagePath = imagePath.replace(/^\/+/, '');
                    if (!imagePath.startsWith('uploads/')) imagePath = 'uploads/' + imagePath;
                    wrapper.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;"><img src="/${imagePath}" style="width:100%;height:100%;object-fit:contain;"></div>`;
                    if (currentContent.display_duration && currentContent.display_duration > 0) {
                        currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                    }
                    return;
                }
            }
            let currentPairIndex = 0;
            let timeoutIds = [];
            const totalPairs = Math.ceil(images.length / imagesPerView);

            function displayPair(pairIndex) {
                const startIdx = pairIndex * imagesPerView;
                const endIdx = Math.min(startIdx + imagesPerView, images.length);
                const currentImages = images.slice(startIdx, endIdx);
                let html = '';
                const gap = 15,
                    padding = 20;
                if (layoutData.type === '4-image') html = `<div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
                else if (layoutData.type === '2-image') html = `<div style="display:grid;grid-template-columns:1fr 1fr;width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
                else if (layoutData.type === '3-image') html = `<div style="display:grid;grid-template-columns:1fr 1fr 1fr;width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
                for (let i = 0; i < imagesPerView; i++) {
                    if (i < currentImages.length) {
                        let imagePath = currentImages[i].path || currentImages[i].image_path;
                        if (imagePath) {
                            imagePath = imagePath.replace(/^\/+/, '');
                            if (!imagePath.startsWith('uploads/')) imagePath = 'uploads/' + imagePath;
                            html += `<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden;background:#0a0a0a;border-radius:16px;"><img src="/${imagePath}" style="width:100%;height:100%;object-fit:contain;"></div>`;
                        }
                    } else {
                        html += `<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#2a2a2a;border-radius:16px;color:#666;"><div style="text-align:center;">Empty</div></div>`;
                    }
                }
                html += `</div>`;
                wrapper.innerHTML = html;
                const duration = (currentImages[0]?.duration || 10) * 1000;
                timeoutIds.push(setTimeout(() => displayPair((pairIndex + 1) % totalPairs), duration));
            }
            if (window.pairTimeoutIds) window.pairTimeoutIds.forEach(id => clearTimeout(id));
            window.pairTimeoutIds = timeoutIds;
            displayPair(0);
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => {
                    if (window.pairTimeoutIds) window.pairTimeoutIds.forEach(id => clearTimeout(id));
                    loadNextContent();
                }, currentContent.display_duration * 1000));
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
            const gap = 15,
                padding = 20;
            if (layoutType === '4-image') html = `<div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
            else if (layoutType === '2-image') html = `<div style="display:grid;grid-template-columns:1fr 1fr;width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
            else {
                const columns = layoutType === '3-image' ? '1fr 1fr 1fr' : '1fr 1fr';
                html = `<div style="display:grid;grid-template-columns:${columns};width:100vw;height:100vh;gap:${gap}px;padding:${padding}px;background:#000;box-sizing:border-box;">`;
            }
            images.forEach((image) => {
                let imagePath = image.path || image.image_path;
                if (imagePath) {
                    imagePath = imagePath.replace(/^\/+/, '');
                    if (!imagePath.startsWith('uploads/')) imagePath = 'uploads/' + imagePath;
                    html += `<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden;background:#0a0a0a;border-radius:16px;"><img src="/${imagePath}" style="width:100%;height:100%;object-fit:contain;"></div>`;
                }
            });
            if (layoutType === '4-image' && images.length < 4) {
                for (let i = images.length; i < 4; i++) html += `<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#2a2a2a;border-radius:16px;color:#666;"><div style="text-align:center;">Empty</div></div>`;
            }
            html += `</div>`;
            wrapper.innerHTML = html;
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            let videoId = extractYouTubeId(currentContent.content_data);
            if (!videoId) {
                loadMessage();
                return;
            }
            const embedUrl = `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&muted=1&loop=1&playlist=${videoId}&controls=1&rel=0`;
            wrapper.innerHTML = `<div class="youtube-container"><iframe class="youtube-iframe" src="${embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe></div>`;
            if (currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function loadLocalVideo() {
            const wrapper = document.getElementById('contentWrapper');
            let videoData;
            try {
                videoData = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
            } catch (e) {
                videoData = {
                    file_path: currentContent.content_data
                };
            }
            let videoPath = videoData.file_path || videoData.path;
            if (!videoPath) {
                loadMessage();
                return;
            }
            videoPath = videoPath.replace(/\\/g, '/').replace(/^\/+/, '');
            if (!videoPath.startsWith('uploads/') && !videoPath.startsWith('videos/')) {
                videoPath = 'uploads/videos/' + videoPath.replace(/^uploads\/?/, '');
            }
            videoPath = videoPath.replace(/\/+/g, '/');
            const fullUrl = '/' + videoPath;
            wrapper.innerHTML = `<div class="local-video-container"><video id="localVideo" autoplay playsinline loop controls preload="auto" style="width:100%;height:100%;object-fit:contain;"><source src="${fullUrl}" type="video/mp4"></video></div>`;
            const video = document.getElementById('localVideo');
            if (!video) return;
            let durationTimeoutSet = false;

            function setDurationTimeout() {
                if (durationTimeoutSet) return;
                durationTimeoutSet = true;
                const contentDuration = currentContent.display_duration;
                if (contentDuration && contentDuration > 0) {
                    const videoDuration = isFinite(video.duration) && video.duration > 0 ? video.duration * 1000 : 0;
                    const waitMs = Math.max(contentDuration * 1000, videoDuration);
                    currentTimeouts.push(setTimeout(() => {
                        video.pause();
                        loadNextContent();
                    }, waitMs));
                }
            }
            video.addEventListener('loadedmetadata', () => {
                setDurationTimeout();
            });
            currentTimeouts.push(setTimeout(() => {
                setDurationTimeout();
            }, 5000));
            video.load();
            video.play().catch(err => {
                if (err.name === 'NotAllowedError') {
                    const btn = document.createElement('div');
                    btn.innerHTML = '▶️ Click to Play';
                    btn.style.cssText = 'position:fixed;bottom:30px;right:30px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:12px 24px;border-radius:40px;cursor:pointer;z-index:9999;font-weight:bold;';
                    btn.onclick = () => {
                        video.play();
                        btn.remove();
                    };
                    document.body.appendChild(btn);
                    setTimeout(() => btn.remove(), 15000);
                }
            });
        }

        function loadPDF() {
            const wrapper = document.getElementById('contentWrapper');
            let pdfData;

            try {
                pdfData = typeof currentContent.content_data === 'string' ? JSON.parse(currentContent.content_data) : currentContent.content_data;
            } catch (e) {
                pdfData = {
                    file_path: currentContent.content_data
                };
            }

            let filePath = pdfData.file_path;
            if (!filePath) {
                console.error('No file path found in PDF data');
                showPDFNotFoundError(wrapper);
                return;
            }

            // Clean up the file path
            filePath = filePath.replace(/^\/+/, '');
            filePath = filePath.replace(/\\/g, '/');

            // Get the filename from the path
            const fileName = filePath.split('/').pop();

            // Try multiple possible paths where the PDF might be located
            const possiblePaths = [
                filePath, // Original path from DB
                'uploads/pdf/' + fileName, // Correct PDF directory
                'uploads/' + fileName, // Main uploads directory
                'uploads/pdf/' + filePath.replace(/^uploads\/?/, ''), // Remove uploads prefix
                'pdf/' + fileName // Just the pdf folder
            ];

            let currentPathIndex = 0;

            function tryNextPath() {
                if (currentPathIndex >= possiblePaths.length) {
                    console.error('All PDF paths failed');
                    showPDFNotFoundError(wrapper);
                    return;
                }

                const testPath = possiblePaths[currentPathIndex].replace(/^\/+/, '');
                const fullUrl = window.location.origin + '/' + testPath;

                console.log(`Trying PDF path ${currentPathIndex + 1}: ${fullUrl}`);

                fetch(fullUrl, {
                        method: 'HEAD'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        // File found, load it
                        console.log(`PDF found at: ${fullUrl}`);
                        loadPDFDocument(fullUrl, wrapper);
                    })
                    .catch(error => {
                        console.log(`Path ${currentPathIndex + 1} failed:`, error.message);
                        currentPathIndex++;
                        tryNextPath();
                    });
            }

            function loadPDFDocument(pdfUrl, wrapper) {
                console.log('Loading PDF from URL:', pdfUrl);
                wrapper.innerHTML = `<div class="pdf-container"><div class="pdf-loading">Loading PDF...</div></div>`;

                pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                    pdfDoc = pdf;
                    totalPages = pdf.numPages;
                    currentPageIndex = 0;
                    console.log('PDF loaded successfully, pages:', totalPages);

                    if (pdfRotationInterval) clearInterval(pdfRotationInterval);

                    if (totalPages === 1) {
                        renderSinglePDFPage();
                        if (currentContent.display_duration && currentContent.display_duration > 0) {
                            currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                        }
                    } else {
                        renderPDFPages();
                        startPDFRotation();
                    }
                }).catch(function(error) {
                    console.error('PDF.js error:', error);
                    showPDFNotFoundError(wrapper);
                });
            }

            function showPDFNotFoundError(wrapper) {
                wrapper.innerHTML = `
            <div class="message-container">
                <div class="message-card memo">
                    <div class="message-icon">📄</div>
                    <div class="message-text">PDF Not Found</div>
                    <div style="margin-top: 15px; font-size: 14px; color: #ff6b6b;">
                        The PDF file could not be located.
                    </div>
                    <div style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                        <div style="font-size: 14px; line-height: 1.6;">
                            💡 <strong>Solution:</strong><br>
                            Please re-upload this PDF file using the admin panel.
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button onclick="window.location.href='/lmt/lmtadmin.php'" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                            📤 Upload PDF Again
                        </button>
                    </div>
                    <div style="margin-top: 15px; font-size: 12px; opacity: 0.6;">
                        Moving to next content in 10 seconds...
                    </div>
                </div>
            </div>
        `;

                if (currentContent.display_duration > 0) {
                    currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                }
            }

            // Start trying paths
            tryNextPath();
        }

        function showPDFError(wrapper, errorMsg) {
            wrapper.innerHTML = `
        <div class="message-container">
            <div class="message-card memo">
                <div class="message-icon">📄</div>
                <div class="message-text">Error loading PDF</div>
                <div style="margin-top: 15px; font-size: 14px; color: #ff6b6b;">${escapeHtml(errorMsg)}</div>
                <div style="margin-top: 10px; font-size: 12px; opacity: 0.7;">Moving to next content...</div>
            </div>
        </div>
    `;
        }

        function showPDFError(wrapper, errorMsg) {
            wrapper.innerHTML = `
        <div class="message-container">
            <div class="message-card memo">
                <div class="message-icon">📄</div>
                <div class="message-text">Error loading PDF</div>
                <div style="margin-top: 15px; font-size: 14px; color: #ff6b6b;">${escapeHtml(errorMsg)}</div>
                <div style="margin-top: 10px; font-size: 12px; opacity: 0.7;">Moving to next content...</div>
            </div>
        </div>
    `;
        }

        function renderSinglePDFPage() {
            if (!pdfDoc) return;
            const wrapper = document.getElementById('contentWrapper');
            wrapper.innerHTML = '<div class="pdf-container"><div class="pdf-viewer single-page"></div></div>';
            const viewer = wrapper.querySelector('.pdf-viewer');
            pdfDoc.getPage(1).then(function(page) {
                const containerWidth = window.innerWidth - 80;
                const containerHeight = window.innerHeight - 120;
                const viewport = page.getViewport({
                    scale: 1
                });
                const scale = Math.min(containerWidth / viewport.width, containerHeight / viewport.height, 3.0);
                const scaledViewport = page.getViewport({
                    scale: scale
                });
                const pageWrapper = document.createElement('div');
                pageWrapper.className = 'pdf-page-wrapper single';
                pageWrapper.style.width = scaledViewport.width + 'px';
                pageWrapper.style.height = scaledViewport.height + 'px';
                const canvas = document.createElement('canvas');
                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;
                pageWrapper.appendChild(canvas);
                viewer.appendChild(pageWrapper);
                page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: scaledViewport
                });
            });
        }

        function renderPDFPages() {
            if (!pdfDoc) return;
            const wrapper = document.getElementById('contentWrapper');
            const startPage = currentPageIndex * 2 + 1;
            const endPage = Math.min(startPage + 1, totalPages);
            if (startPage > totalPages) {
                currentPageIndex = 0;
                renderPDFPages();
                return;
            }
            wrapper.innerHTML = '<div class="pdf-container"><div class="pdf-viewer two-pages"></div></div>';
            const viewer = wrapper.querySelector('.pdf-viewer');
            const pagePromises = [];
            for (let pageNum = startPage; pageNum <= endPage; pageNum++) {
                pagePromises.push(pdfDoc.getPage(pageNum));
            }
            Promise.all(pagePromises).then(function(pages) {
                const containerWidth = (window.innerWidth - 100) / pages.length;
                const containerHeight = window.innerHeight - 140;
                pages.forEach(function(page) {
                    const viewport = page.getViewport({
                        scale: 1
                    });
                    const scale = Math.min(containerWidth / viewport.width, containerHeight / viewport.height, 2.5);
                    const scaledViewport = page.getViewport({
                        scale: scale
                    });
                    const pageWrapper = document.createElement('div');
                    pageWrapper.className = 'pdf-page-wrapper';
                    pageWrapper.style.width = scaledViewport.width + 'px';
                    pageWrapper.style.height = scaledViewport.height + 'px';
                    const canvas = document.createElement('canvas');
                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    pageWrapper.appendChild(canvas);
                    viewer.appendChild(pageWrapper);
                    page.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport: scaledViewport
                    });
                });
                const pageIndicator = document.createElement('div');
                pageIndicator.className = 'pdf-page-number';
                pageIndicator.textContent = `Pages ${startPage}-${endPage} of ${totalPages}`;
                wrapper.querySelector('.pdf-container').appendChild(pageIndicator);
            });
        }

        function startPDFRotation() {
            if (pdfRotationInterval) clearInterval(pdfRotationInterval);
            pdfRotationInterval = setInterval(() => {
                if (pdfDoc) {
                    const totalSets = Math.ceil(totalPages / 2);
                    currentPageIndex = (currentPageIndex + 1) % totalSets;
                    renderPDFPages();
                }
            }, 20000);
            currentTimeouts.push(pdfRotationInterval);
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => {
                    if (pdfRotationInterval) clearInterval(pdfRotationInterval);
                    loadNextContent();
                }, currentContent.display_duration * 1000));
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
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
            }
        }

        function loadNextContent() {
            const nextId = currentContent.next_content_id;
            if (nextId && !isNaN(parseInt(nextId)) && parseInt(nextId) > 0) {
                window.location.href = '?id=' + nextId + '&mode=' + currentMode;
            } else {
                window.location.href = '?mode=' + currentMode;
            }
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
                    if (!this.classList.contains('active')) this.style.transform = 'translateX(-96%)';
                }, 1000);
            });
            navDropdown.addEventListener('click', function(e) {
                if (!e.target.closest('.mode-option')) {
                    this.classList.toggle('active');
                    this.style.transform = this.classList.contains('active') ? 'translateX(0)' : 'translateX(-96%)';
                }
            });
        });
    </script>
</body>

</html>