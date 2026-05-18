<?php
require_once 'config/db.php';

// Handle version check API request (MUST be at the very top before ANY output)
if (isset($_GET['action']) && $_GET['action'] === 'check_version') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'lmt';
    $current_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;

    $stmt = $pdo->prepare("SELECT version FROM content_version WHERE admin_role = ?");
    $stmt->execute([$mode]);
    $version = $stmt->fetch();
    $server_version = $version ? (int)$version['version'] : 1;

    echo json_encode([
        'has_update' => ($server_version > $current_version),
        'server_version' => $server_version
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

// Handle content by ID - but always validate that the ID is in the active chain
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    // First, verify this content exists and is active for this mode
    $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND admin_role = ? AND is_active = 1");
    $stmt->execute([$id, $current_mode]);
    $current_content = $stmt->fetch();

    if (!$current_content) {
        // If invalid ID, redirect to base URL
        header('Location: ?mode=' . $current_mode);
        exit();
    }
} else {
    // No ID specified - get the first active content based on display order
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

// If no content, use default
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

// Get current version for polling (to detect order changes)
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
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: #000;
        }

        .description-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
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
        }

        .description-bar.visible {
            opacity: 1;
            visibility: visible;
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-left: 8px solid #ff0000;
        }

        .caution {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            border-left: 8px solid #ff9800;
        }

        .memo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-left: 8px solid #2196f3;
        }

        .congratulation {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
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
            color: #333;
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
        }

        function showDescription(description) {
            const descBar = document.getElementById('descriptionBar');
            if (description && description.trim() !== '') {
                descBar.innerHTML = description;
                descBar.classList.add('visible');
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
                fetch(window.location.pathname + '?action=check_version&mode=' + currentMode + '&version=' + currentVersion, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.has_update) {
                            console.log('Update detected, refreshing');
                            window.location.href = '?mode=' + currentMode;
                        }
                    })
                    .catch(error => {});
            }, 10000);
        }

        function loadContent() {
            const wrapper = document.getElementById('contentWrapper');
            clearAllTimeouts();
            localVideoUnlockAttempts = 0;

            if (!currentContent || !currentContent.content_type) {
                wrapper.innerHTML = `
                    <div class="message-container">
                        <div class="message-card memo">
                            <div class="message-icon">📺</div>
                            <div class="message-text">No content available for ${currentMode.toUpperCase()} mode.<br>Please check back later.</div>
                        </div>
                    </div>
                `;
                return;
            }

            if ((currentContent.content_type === 'slideshow' || currentContent.content_type === 'ppt') && currentContent.description) {
                showDescription(currentContent.description);
            } else {
                showDescription('');
            }

            if (currentContent.content_type === 'slideshow') {
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
            } else if (currentContent.content_type === 'youtube') {
                loadYouTube();
            } else if (currentContent.content_type === 'local_video') {
                loadLocalVideo();
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

        function loadSlideshowPairs(layoutData) {
            const wrapper = document.getElementById('contentWrapper');
            const images = layoutData.images || [];
            const imagesPerView = layoutData.type === '2-image' ? 2 : (layoutData.type === '3-image' ? 3 : 4);

            if (images.length === 0) {
                loadMessage();
                return;
            }

            let currentPairIndex = 0;
            let timeoutIds = [];
            const totalPairs = Math.ceil(images.length / imagesPerView);

            function displayPair(pairIndex) {
                const startIdx = pairIndex * imagesPerView;
                const endIdx = Math.min(startIdx + imagesPerView, images.length);
                const currentImages = images.slice(startIdx, endIdx);

                let html = '';
                const gap = 15;
                const padding = 20;

                if (layoutData.type === '4-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                } else if (layoutData.type === '2-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                } else if (layoutData.type === '3-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                }

                for (let i = 0; i < imagesPerView; i++) {
                    if (i < currentImages.length) {
                        let imagePath = currentImages[i].path || currentImages[i].image_path;
                        if (imagePath) {
                            imagePath = imagePath.replace(/^\/+/, '');
                            if (!imagePath.startsWith('uploads/')) {
                                imagePath = 'uploads/' + imagePath;
                            }
                            html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; overflow: hidden; background: #0a0a0a; border-radius: 16px;">
                                        <img src="/${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.parentElement.innerHTML='<div style=\'color:#666;text-align:center;\'>⚠️</div>'">
                                    </div>`;
                        }
                    } else {
                        html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #2a2a2a; border-radius: 16px; color: #666;"><div>Empty</div></div>`;
                    }
                }
                html += `</div>`;
                wrapper.innerHTML = html;

                const duration = (currentImages[0]?.duration || 10) * 1000;
                const timeoutId = setTimeout(() => displayPair((pairIndex + 1) % totalPairs), duration);
                timeoutIds.push(timeoutId);
            }

            if (window.pairTimeoutIds) {
                window.pairTimeoutIds.forEach(id => clearTimeout(id));
            }
            window.pairTimeoutIds = timeoutIds;
            displayPair(0);

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const overallTimeoutId = setTimeout(() => {
                    if (window.pairTimeoutIds) {
                        window.pairTimeoutIds.forEach(id => clearTimeout(id));
                    }
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(overallTimeoutId);
                window.pairTimeoutIds.push(overallTimeoutId);
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
            const gap = 15;
            const padding = 20;

            if (layoutType === '4-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else if (layoutType === '2-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else {
                const columns = layoutType === '3-image' ? '1fr 1fr 1fr' : '1fr 1fr';
                html = `<div style="display: grid; grid-template-columns: ${columns}; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            }

            images.forEach((image, index) => {
                let imagePath = image.path || image.image_path;
                if (imagePath) {
                    imagePath = imagePath.replace(/^\/+/, '');
                    if (!imagePath.startsWith('uploads/')) {
                        imagePath = 'uploads/' + imagePath;
                    }
                    html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; overflow: hidden; background: #0a0a0a; border-radius: 16px;">
                                <img src="/${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.parentElement.innerHTML='<div style=\'color:#666;text-align:center;\'>⚠️</div>'">
                            </div>`;
                }
            });

            if (layoutType === '4-image' && images.length < 4) {
                for (let i = images.length; i < 4; i++) {
                    html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #2a2a2a; border-radius: 16px; color: #666;">
                                <div style="text-align: center;">Empty Slot</div>
                            </div>`;
                }
            }

            html += `</div>`;
            wrapper.innerHTML = html;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            let videoId = extractYouTubeId(currentContent.content_data);
            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&loop=1&playlist=${videoId}&controls=0&rel=0&modestbranding=1&playsinline=1`;
            wrapper.innerHTML = `<div class="youtube-container"><iframe class="youtube-iframe" src="${embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>`;
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

            videoPath = videoPath.replace(/^\/+/, '');
            if (!videoPath.startsWith('uploads/') && !videoPath.startsWith('videos/')) {
                videoPath = 'uploads/videos/' + videoPath.replace(/^uploads\/?/, '');
            }

            wrapper.innerHTML = `<div class="local-video-container"><video id="localVideo" autoplay playsinline loop controls style="width:100%;height:100%;object-fit:contain;"><source src="/${videoPath}" type="video/mp4"></video></div>`;

            const video = document.getElementById('localVideo');
            if (video) {
                video.load();
                video.play().catch(e => console.log('Play error:', e));
            }

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
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

            const pdfUrl = window.location.origin + filePath;
            wrapper.innerHTML = `<div class="pdf-container"><div class="pdf-loading">Loading PDF...</div></div>`;

            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                currentPageIndex = 0;

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
                console.error('Error loading PDF:', error);
                wrapper.innerHTML = `<div class="message-container"><div class="message-card memo"><div class="message-icon">📄</div><div class="message-text">Error loading PDF</div></div></div>`;
                if (currentContent.display_duration > 0) {
                    currentTimeouts.push(setTimeout(() => loadNextContent(), currentContent.display_duration * 1000));
                }
            });
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

                const scaleX = containerWidth / viewport.width;
                const scaleY = containerHeight / viewport.height;
                const scale = Math.min(scaleX, scaleY, 3.0);

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
                    const scaleX = containerWidth / viewport.width;
                    const scaleY = containerHeight / viewport.height;
                    const scale = Math.min(scaleX, scaleY, 2.5);

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
                pageIndicator.textContent = `Page ${startPage}-${endPage} of ${totalPages}`;
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
                const overallTimeoutId = setTimeout(() => {
                    if (pdfRotationInterval) clearInterval(pdfRotationInterval);
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(overallTimeoutId);
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
                    if (!this.classList.contains('active')) {
                        this.style.transform = 'translateX(-96%)';
                    }
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