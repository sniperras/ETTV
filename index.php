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
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: #000;
        }

        /* Description Bar - Persistent */
        .description-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            color: white;
            text-align: center;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: 500;
            z-index: 1001;
            border-bottom: 2px solid rgba(102, 126, 234, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .description-bar.visible {
            opacity: 1;
            visibility: visible;
        }

        /* Add padding to content wrapper when description is visible */
        .description-bar.visible+.display-container .content-wrapper {
            padding-top: 60px;
        }

        .content-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: padding-top 0.3s ease;
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

        .video-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* PDF Horizontal Grid Styles - 4 pages in a row */
        .pdf-container {
            width: 100%;
            height: 100%;
            background: #000;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .pdf-horizontal-grid {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100%;
            gap: 15px;
            padding: 20px;
            background: #1a1a1a;
        }

        .pdf-page {
            flex: 1;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .pdf-page img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .pdf-page-number {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 10;
            font-weight: bold;
        }

        .pdf-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 18px;
            text-align: center;
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

            .pdf-horizontal-grid {
                gap: 8px;
                padding: 10px;
            }

            .description-bar {
                font-size: 14px;
                padding: 8px 15px;
            }
        }
    </style>
    <!-- PDF.js library for PDF processing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    </script>
</head>

<body>
    <div class="nav-dropdown" id="navDropdown">
        <div class="nav-content">
            <h3>Select Display</h3>
            <button class="mode-option" onclick="switchMode('lmt')">LMT Display</button>
            <button class="mode-option" onclick="switchMode('bmt')">BMT Display</button>
        </div>
    </div>

    <!-- Description Bar - Persistent (stays visible) -->
    <div id="descriptionBar" class="description-bar"></div>

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

        // PDF variables
        let pdfDoc = null;
        let currentPageSet = 0;
        let totalPages = 0;
        let pagesPerView = 4; // 4 pages horizontally

        function clearAllTimeouts() {
            currentTimeouts.forEach(timeout => clearTimeout(timeout));
            currentTimeouts = [];

            if (currentYouTubePlayer && typeof currentYouTubePlayer.destroy === 'function') {
                try {
                    currentYouTubePlayer.destroy();
                } catch (e) {}
                currentYouTubePlayer = null;
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

        function updateCurrentContent(data) {
            currentContent = data.content;
            currentSlides = data.slides || [];
            currentLayoutData = null;
            pdfDoc = null;
            currentPageSet = 0;

            // Show description for slideshow and PDF content - PERSISTENT (no auto-hide)
            if ((currentContent.content_type === 'slideshow' || currentContent.content_type === 'ppt') && currentContent.description) {
                showDescription(currentContent.description);
            } else {
                showDescription('');
            }

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
                        },
                        'onStateChange': function(event) {
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

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    console.log('Video duration expired, loading next content');
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

            // Full URL to PDF
            const pdfUrl = window.location.origin + filePath;

            // Show loading
            wrapper.innerHTML = `
                <div class="pdf-container">
                    <div class="pdf-loading">Loading PDF...</div>
                </div>
            `;

            // Load PDF using pdf.js
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                currentPageSet = 0;

                console.log('PDF loaded. Total pages:', totalPages);

                // Display first set of pages (horizontal row)
                displayPDFPageSet();

                // Schedule next set of pages
                scheduleNextPDFSet();

            }).catch(function(error) {
                console.error('Error loading PDF:', error);
                wrapper.innerHTML = `
                    <div class="message-container">
                        <div class="message-card memo">
                            <div class="message-icon">📄</div>
                            <div class="message-text">Error loading PDF. Please check the file.</div>
                        </div>
                    </div>
                `;
                if (currentContent.display_duration > 0) {
                    const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                    currentTimeouts.push(timeoutId);
                }
            });
        }

        function displayPDFPageSet() {
            if (!pdfDoc) return;

            const startPage = currentPageSet * pagesPerView + 1;
            const endPage = Math.min(startPage + pagesPerView - 1, totalPages);

            if (startPage > totalPages) {
                // All pages shown, move to next content
                loadNextContent();
                return;
            }

            const wrapper = document.getElementById('contentWrapper');

            // Create horizontal grid container
            let html = '<div class="pdf-container"><div class="pdf-horizontal-grid">';

            // Load each page in the current set
            const pagePromises = [];
            for (let pageNum = startPage; pageNum <= endPage; pageNum++) {
                pagePromises.push(
                    pdfDoc.getPage(pageNum).then(function(page) {
                        // Calculate scale to fit within the viewport width
                        // Each page takes 1/4 of the screen width
                        const containerWidth = window.innerWidth / pagesPerView - 30; // subtract gap
                        const viewport = page.getViewport({
                            scale: 1
                        });
                        const scale = containerWidth / viewport.width;
                        const scaledViewport = page.getViewport({
                            scale: scale
                        });

                        // Create canvas for rendering
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.width = scaledViewport.width;
                        canvas.height = scaledViewport.height;

                        return page.render({
                            canvasContext: context,
                            viewport: scaledViewport
                        }).promise.then(function() {
                            return {
                                pageNum: pageNum,
                                imageData: canvas.toDataURL()
                            };
                        });
                    })
                );
            }

            Promise.all(pagePromises).then(function(pagesData) {
                let innerHtml = '<div class="pdf-container"><div class="pdf-horizontal-grid">';
                pagesData.forEach(function(pageData) {
                    innerHtml += `
                        <div class="pdf-page">
                            <img src="${pageData.imageData}" alt="Page ${pageData.pageNum}">
                        </div>
                    `;
                });
                // Add empty placeholders if less than 4 pages
                for (let i = pagesData.length; i < pagesPerView; i++) {
                    innerHtml += `<div class="pdf-page" style="background: #333; display: flex; align-items: center; justify-content: center; color: #666;">
                                    <span>End</span>
                                  </div>`;
                }
                innerHtml += `</div><div class="pdf-page-number">Pages ${startPage}-${endPage} of ${totalPages}</div></div>`;
                wrapper.innerHTML = innerHtml;
            });
        }

        function scheduleNextPDFSet() {
            // Clear any existing PDF timeout
            if (window.pdfTimeout) {
                clearTimeout(window.pdfTimeout);
            }

            // Schedule next set after display_duration (default 60 seconds = 1 minute)
            const duration = (currentContent.display_duration && currentContent.display_duration > 0) ?
                currentContent.display_duration * 1000 :
                60000; // 1 minute default

            window.pdfTimeout = setTimeout(() => {
                if (pdfDoc) {
                    currentPageSet++;
                    displayPDFPageSet();
                    scheduleNextPDFSet(); // Schedule the next one
                }
            }, duration);
            currentTimeouts.push(window.pdfTimeout);
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
            // Show initial description if present (persistent - no auto-hide)
            if ((currentContent.content_type === 'slideshow' || currentContent.content_type === 'ppt') && currentContent.description) {
                showDescription(currentContent.description);
            }

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