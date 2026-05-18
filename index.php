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
        /* Your existing styles remain the same */
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
            gap: 20px;
            padding: 30px;
            background: #0a0a0a;
        }

        .pdf-page {
            flex: 1;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            position: relative;
        }

        .pdf-page img {
            width: 100%;
            height: 100%;
            object-fit: contain;
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

        .pdf-container canvas {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
        }

        .pdf-page img,
        .pdf-page canvas {
            width: 100%;
            height: auto;
            image-rendering: high-quality;
        }

        /* For single page PDF */
        .pdf-container.single-page {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }

        .pdf-container.single-page canvas {
            max-width: 90vw;
            max-height: 90vh;
            object-fit: contain;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border-radius: 8px;
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

            .pdf-horizontal-grid {
                gap: 12px;
                padding: 15px;
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

        // Suppress PDF.js warnings
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
        let currentPageSet = 0;
        let totalPages = 0;
        let pagesPerView = 2;
        let pdfRotationInterval = null;

        // Update clearAllTimeouts to clear PDF rotation
        function clearAllTimeouts() {
            currentTimeouts.forEach(timeout => clearTimeout(timeout));
            currentTimeouts = [];
            // Clear slideshow pairs timeouts
            if (window.pairTimeoutIds) {
                window.pairTimeoutIds.forEach(id => clearTimeout(id));
                window.pairTimeoutIds = [];
            }
            // Clear PDF rotation interval
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
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.has_update) {
                            console.log('Update detected, refreshing to base URL');
                            window.location.href = '?mode=' + currentMode;
                        }
                    })
                    .catch(error => {
                        // Don't log every error to avoid console spam
                        // console.log('Polling check failed, will retry');
                    });
            }, 10000); // Increase to 10 seconds to reduce frequency
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

            // Show description
            if ((currentContent.content_type === 'slideshow' || currentContent.content_type === 'ppt') && currentContent.description) {
                showDescription(currentContent.description);
            } else {
                showDescription('');
            }

            if (currentContent.content_type === 'slideshow') {
                // First priority: Use layout data from PHP if available
                if (currentLayoutData && currentLayoutData.type && currentLayoutData.type !== 'slideshow') {
                    console.log('Loading layout from PHP:', currentLayoutData);
                    // For multi-image layouts (2-image, 3-image, 4-image), use slideshow pairs
                    if (currentLayoutData.type === '2-image' || currentLayoutData.type === '3-image' || currentLayoutData.type === '4-image') {
                        loadSlideshowPairs(currentLayoutData);
                    } else {
                        loadMultiImageLayout(currentLayoutData);
                    }
                    return;
                }

                // Check if we have slides from DB (traditional slideshow)
                if (currentSlides && currentSlides.length > 0) {
                    loadSlideshow();
                    return;
                }

                // Try to parse content_data
                try {
                    const parsed = typeof currentContent.content_data === 'string' ?
                        JSON.parse(currentContent.content_data) :
                        currentContent.content_data;

                    console.log('Parsed content_data:', parsed);

                    if (parsed) {
                        // Check if it's a multi-image layout
                        if (parsed.type && (parsed.type === '2-image' || parsed.type === '3-image' || parsed.type === '4-image') && parsed.images && parsed.images.length > 0) {
                            console.log('Detected multi-image layout from content_data');
                            loadSlideshowPairs(parsed);
                            return;
                        }
                        // Check if it's a regular slideshow with images array
                        else if (parsed.images && parsed.images.length > 0) {
                            console.log('Detected slideshow images from content_data');
                            currentSlides = parsed.images;
                            loadSlideshow();
                            return;
                        }
                    }
                } catch (e) {
                    console.log('Failed to parse content_data:', e);
                }

                // Final fallback
                console.log('No valid slideshow data found, showing message');
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

            console.log('Loading slideshow pairs:', imagesPerView, 'images per view, Total images:', images.length);

            if (images.length === 0) {
                loadMessage();
                return;
            }

            let currentPairIndex = 0;
            let timeoutIds = [];

            // Calculate how many pairs we need
            const totalPairs = Math.ceil(images.length / imagesPerView);

            function displayPair(pairIndex) {
                const startIdx = pairIndex * imagesPerView;
                const endIdx = Math.min(startIdx + imagesPerView, images.length);
                const currentImages = images.slice(startIdx, endIdx);

                console.log(`Displaying pair ${pairIndex + 1}/${totalPairs}, images ${startIdx + 1}-${endIdx} of ${images.length}`);

                let html = '';
                const gap = 15;
                const padding = 20;

                // Create grid based on layout type
                if (layoutData.type === '4-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                } else if (layoutData.type === '2-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                } else if (layoutData.type === '3-image') {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                } else {
                    html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
                }

                // Add images for this pair
                for (let i = 0; i < imagesPerView; i++) {
                    if (i < currentImages.length) {
                        let imagePath = currentImages[i].path || currentImages[i].image_path;
                        if (!imagePath) {
                            console.error('No image path for index', i);
                            continue;
                        }

                        // Fix path
                        imagePath = imagePath.replace(/^\/+/, '');
                        if (!imagePath.startsWith('uploads/')) {
                            imagePath = 'uploads/' + imagePath;
                        }

                        html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; overflow: hidden; background: #0a0a0a; border-radius: 16px;">
                            <img src="/${imagePath}" 
                                 style="width: 100%; height: 100%; object-fit: contain;" 
                                 onerror="this.onerror=null; this.parentElement.style.background='#333'; this.parentElement.innerHTML='<div style=\'color:#666;text-align:center;padding:20px;\'>⚠️<br>Image ${startIdx + i + 1}<br>Failed to load</div>';">
                        </div>`;
                    } else {
                        // Empty slot
                        html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #2a2a2a; border-radius: 16px; color: #666;">
                            <div style="text-align: center;">Empty Slot</div>
                        </div>`;
                    }
                }

                html += `</div>`;
                wrapper.innerHTML = html;

                // Schedule next pair
                const duration = (currentImages[0]?.duration || 10) * 1000; // Use individual slide duration or default 10 seconds
                const timeoutId = setTimeout(() => {
                    const nextPair = (pairIndex + 1) % totalPairs;
                    displayPair(nextPair);
                }, duration);

                timeoutIds.push(timeoutId);
            }

            // Clear previous timeouts when function is called again
            if (window.pairTimeoutIds) {
                window.pairTimeoutIds.forEach(id => clearTimeout(id));
            }
            window.pairTimeoutIds = timeoutIds;

            // Start displaying from first pair
            displayPair(0);

            // Set overall timeout to move to next content after display_duration
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const overallTimeoutId = setTimeout(() => {
                    console.log('Overall display duration expired, moving to next content');
                    // Clear all pair timeouts
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

            console.log('Loading multi-image layout:', layoutType, 'Images:', images);

            if (images.length === 0) {
                console.log('No images found for multi-image layout, falling back to message');
                loadMessage();
                return;
            }

            let html = '';
            const gap = 15;
            const padding = 20;

            // Determine grid layout based on type
            if (layoutType === '4-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else if (layoutType === '2-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else if (layoutType === '3-image') {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            } else {
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; width: 100vw; height: 100vh; gap: ${gap}px; padding: ${padding}px; background: #000; box-sizing: border-box;">`;
            }

            // Process each image
            images.forEach((image, index) => {
                // Get the image path
                let imagePath = image.path || image.image_path;

                if (!imagePath) {
                    console.error('No image path found for image:', image);
                    return;
                }

                // Fix the path - remove any leading slashes and ensure it's relative
                imagePath = imagePath.replace(/^\/+/, '');

                // If path doesn't start with 'uploads/', add it
                if (!imagePath.startsWith('uploads/')) {
                    imagePath = 'uploads/' + imagePath;
                }

                console.log(`Image ${index + 1} final path:`, imagePath);

                html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; overflow: hidden; background: #0a0a0a; border-radius: 16px;">
                    <img src="/${imagePath}" 
                         style="width: 100%; height: 100%; object-fit: contain;" 
                         onerror="this.onerror=null; this.parentElement.style.background='#333'; this.parentElement.innerHTML='<div style=\'color:#666;text-align:center;padding:20px;\'>⚠️<br>Image ${index + 1}<br>Failed to load</div>';">
                </div>`;
            });

            // Fill empty slots for 4-image layout
            if (layoutType === '4-image' && images.length < 4) {
                for (let i = images.length; i < 4; i++) {
                    html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #2a2a2a; border-radius: 16px; color: #666;">
                        <div style="text-align: center;">Empty Slot</div>
                    </div>`;
                }
            }

            html += `</div>`;
            wrapper.innerHTML = html;

            // Set timeout to move to next content
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            let videoUrl = currentContent.content_data;
            let videoId = extractYouTubeId(videoUrl);

            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&loop=1&playlist=${videoId}&controls=0&rel=0&modestbranding=1&playsinline=1`;

            wrapper.innerHTML = `
                <div class="youtube-container">
                    <iframe class="youtube-iframe" 
                            src="${embedUrl}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen>
                    </iframe>
                </div>
            `;

            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    console.log('Video duration expired, loading next content');
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadLocalVideo() {
            const wrapper = document.getElementById('contentWrapper');
            let videoData;

            try {
                videoData = typeof currentContent.content_data === 'string' ?
                    JSON.parse(currentContent.content_data) :
                    currentContent.content_data;
            } catch (e) {
                videoData = {
                    file_path: currentContent.content_data
                };
            }

            let videoPath = videoData.file_path || videoData.path;
            if (!videoPath) {
                console.error('No video path found');
                loadMessage();
                return;
            }

            // Clean up the path
            videoPath = videoPath.replace(/^\/+/, '');

            // Ensure it has the correct prefix
            if (!videoPath.startsWith('uploads/') && !videoPath.startsWith('videos/')) {
                videoPath = 'uploads/videos/' + videoPath.replace(/^uploads\/?/, '');
            }

            console.log('Loading video from path:', videoPath);

            wrapper.innerHTML = `
        <div class="local-video-container">
            <video id="localVideo" autoplay playsinline loop controls style="width:100%;height:100%;object-fit:contain;">
                <source src="/${videoPath}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    `;

            const video = document.getElementById('localVideo');
            if (video) {
                // Load the video
                video.load();

                // Try to play with sound
                const playVideo = () => {
                    video.muted = false;
                    video.volume = 1.0;
                    video.play().catch(e => {
                        console.log('Play failed:', e);
                        // Try with muted first if autoplay is blocked
                        video.muted = true;
                        video.play().then(() => {
                            console.log('Playing muted, will try to unmute');
                            // Try to unmute after a short delay
                            setTimeout(() => {
                                video.muted = false;
                                video.play().catch(() => {});
                            }, 1000);
                        }).catch(err => console.log('Even muted play failed:', err));
                    });
                };

                // Wait for video to be ready
                video.addEventListener('canplay', playVideo);
                video.addEventListener('error', (e) => {
                    console.error('Video error:', e);
                    console.error('Video error code:', video.error ? video.error.code : 'unknown');
                    wrapper.innerHTML = `<div class="message-container"><div class="message-card memo"><div class="message-icon">🎬</div><div class="message-text">Video failed to load<br><small style="font-size:14px;">Path: /${videoPath}</small></div></div></div>`;
                    // Still schedule next content after duration
                    if (currentContent.display_duration && currentContent.display_duration > 0) {
                        const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                        currentTimeouts.push(timeoutId);
                    }
                });
            }

            // Set timeout to move to next content
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    console.log('Video duration expired, loading next content');
                    const video = document.getElementById('localVideo');
                    if (video) {
                        video.pause();
                    }
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

            const pdfUrl = window.location.origin + filePath;
            wrapper.innerHTML = `<div class="pdf-container"><div class="pdf-loading">Loading PDF...</div></div>`;

            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                currentPageSet = 0;

                // Clear any existing rotation interval
                if (pdfRotationInterval) clearInterval(pdfRotationInterval);

                if (totalPages === 1) {
                    // Single page - display centered
                    displaySinglePDFPage();
                    // Schedule next content after display_duration
                    if (currentContent.display_duration && currentContent.display_duration > 0) {
                        const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                        currentTimeouts.push(timeoutId);
                    }
                } else {
                    // Multiple pages - show 2 pages at a time
                    displayPDFPageSet();
                    startPDFRotation();
                }
            }).catch(function(error) {
                console.error('Error loading PDF:', error);
                wrapper.innerHTML = `<div class="message-container"><div class="message-card memo"><div class="message-icon">📄</div><div class="message-text">Error loading PDF</div></div></div>`;
                if (currentContent.display_duration > 0) {
                    const timeoutId = setTimeout(() => loadNextContent(), currentContent.display_duration * 1000);
                    currentTimeouts.push(timeoutId);
                }
            });
        }

        function startPDFRotation() {
            // Clear any existing rotation interval
            if (pdfRotationInterval) clearInterval(pdfRotationInterval);

            // Rotate every 20 seconds (20000 ms)
            pdfRotationInterval = setInterval(() => {
                if (pdfDoc) {
                    const startPage = currentPageSet * pagesPerView + 1;
                    const endPage = Math.min(startPage + pagesPerView - 1, totalPages);

                    // Check if we've reached the end
                    if (endPage >= totalPages) {
                        // Loop back to beginning
                        currentPageSet = 0;
                    } else {
                        currentPageSet++;
                    }
                    displayPDFPageSet();
                }
            }, 20000); // 20 seconds

            currentTimeouts.push(pdfRotationInterval);

            // Set overall timeout to move to next content after display_duration
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const overallTimeoutId = setTimeout(() => {
                    // Stop rotation
                    if (pdfRotationInterval) clearInterval(pdfRotationInterval);
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(overallTimeoutId);
            }
        }

        function displaySinglePDFPage() {
            if (!pdfDoc) return;

            const wrapper = document.getElementById('contentWrapper');
            wrapper.innerHTML = '<div class="pdf-container"><div class="pdf-loading">Rendering page...</div></div>';

            pdfDoc.getPage(1).then(function(page) {
                // Get container dimensions
                const container = wrapper.querySelector('.pdf-container') || wrapper;
                const containerWidth = window.innerWidth - 60;
                const containerHeight = window.innerHeight - 100;

                // Calculate scale to fit page in container
                const viewport = page.getViewport({
                    scale: 1
                });
                const scaleX = containerWidth / viewport.width;
                const scaleY = containerHeight / viewport.height;
                const scale = Math.min(scaleX, scaleY, 2.5); // Max scale 2.5 for quality

                const scaledViewport = page.getViewport({
                    scale: scale
                });

                // Create canvas with higher quality
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;
                canvas.style.width = 'auto';
                canvas.style.height = 'auto';
                canvas.style.maxWidth = '100%';
                canvas.style.maxHeight = '100%';
                canvas.style.objectFit = 'contain';

                wrapper.innerHTML = `
            <div class="pdf-container" style="display: flex; align-items: center; justify-content: center; background: #000;">
                <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                </div>
            </div>
        `;

                const innerDiv = wrapper.querySelector('div div');
                innerDiv.appendChild(canvas);

                page.render({
                    canvasContext: context,
                    viewport: scaledViewport
                });
            });
        }

        function displayPDFPageSet() {
            if (!pdfDoc) return;

            const startPage = currentPageSet * pagesPerView + 1;
            let endPage = Math.min(startPage + pagesPerView - 1, totalPages);

            // Ensure we show 2 pages even if last set has only 1 page
            // For the last page, show it centered
            const isLastSet = startPage > totalPages;

            if (startPage > totalPages) {
                // Loop back to beginning
                currentPageSet = 0;
                displayPDFPageSet();
                return;
            }

            const wrapper = document.getElementById('contentWrapper');
            wrapper.innerHTML = '<div class="pdf-container"><div class="pdf-loading">Rendering pages...</div></div>';

            const container = wrapper.querySelector('.pdf-container');
            container.innerHTML = '';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.background = '#000';

            // If this is the last set and only has 1 page, show it centered
            const isSinglePageInSet = (endPage - startPage + 1) === 1;

            if (isSinglePageInSet) {
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
            } else {
                container.style.flexDirection = 'row';
                container.style.gap = '20px';
                container.style.padding = '30px';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
            }

            const pagePromises = [];
            const pageElements = [];

            for (let pageNum = startPage; pageNum <= endPage; pageNum++) {
                const pageDiv = document.createElement('div');
                if (isSinglePageInSet) {
                    pageDiv.style.display = 'flex';
                    pageDiv.style.alignItems = 'center';
                    pageDiv.style.justifyContent = 'center';
                    pageDiv.style.width = '100%';
                    pageDiv.style.height = '100%';
                } else {
                    pageDiv.style.flex = '1';
                    pageDiv.style.display = 'flex';
                    pageDiv.style.alignItems = 'center';
                    pageDiv.style.justifyContent = 'center';
                    pageDiv.style.background = '#fff';
                    pageDiv.style.borderRadius = '16px';
                    pageDiv.style.overflow = 'hidden';
                    pageDiv.style.boxShadow = '0 8px 32px rgba(0,0,0,0.4)';
                    pageDiv.style.minHeight = '0';
                }
                container.appendChild(pageDiv);
                pageElements.push(pageDiv);

                pagePromises.push(
                    pdfDoc.getPage(pageNum).then(function(page) {
                        return {
                            page: page,
                            pageNum: pageNum
                        };
                    })
                );
            }

            Promise.all(pagePromises).then(function(pagesData) {
                pagesData.forEach(function(pageData, idx) {
                    const page = pageData.page;
                    const pageDiv = pageElements[idx];

                    // Calculate scale based on container
                    let scale;
                    if (isSinglePageInSet) {
                        const containerWidth = window.innerWidth - 60;
                        const containerHeight = window.innerHeight - 100;
                        const viewport = page.getViewport({
                            scale: 1
                        });
                        const scaleX = containerWidth / viewport.width;
                        const scaleY = containerHeight / viewport.height;
                        scale = Math.min(scaleX, scaleY, 2.5);
                    } else {
                        const containerWidth = (window.innerWidth / 2) - 60;
                        const viewport = page.getViewport({
                            scale: 1
                        });
                        scale = (containerWidth / viewport.width) * 1.2; // Slightly larger for better quality
                        scale = Math.min(scale, 2.0);
                    }

                    const scaledViewport = page.getViewport({
                        scale: scale
                    });

                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    canvas.style.width = '100%';
                    canvas.style.height = 'auto';
                    canvas.style.maxWidth = '100%';
                    canvas.style.maxHeight = '100%';
                    canvas.style.objectFit = 'contain';

                    pageDiv.appendChild(canvas);

                    page.render({
                        canvasContext: context,
                        viewport: scaledViewport
                    });
                });

                // Add page indicator
                const pageIndicator = document.createElement('div');
                pageIndicator.className = 'pdf-page-number';
                pageIndicator.textContent = `Pages ${startPage}-${endPage} of ${totalPages}`;
                container.appendChild(pageIndicator);
            });
        }

        function scheduleNextPDFSet() {
            if (window.pdfTimeout) clearTimeout(window.pdfTimeout);
            const duration = (currentContent.display_duration && currentContent.display_duration > 0) ? currentContent.display_duration * 1000 : 60000;
            window.pdfTimeout = setTimeout(() => {
                if (pdfDoc) {
                    currentPageSet++;
                    displayPDFPageSet();
                    scheduleNextPDFSet();
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
            const nextId = currentContent.next_content_id;
            if (nextId && !isNaN(parseInt(nextId)) && parseInt(nextId) > 0) {
                console.log('Moving to next content ID:', nextId);
                // Navigate to next content but keep mode
                window.location.href = '?id=' + nextId + '&mode=' + currentMode;
            } else {
                console.log('End of content chain, restarting from beginning');
                // Go back to base URL to start from the first content
                window.location.href = '?mode=' + currentMode;
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