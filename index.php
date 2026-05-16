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

// Get slides if slideshow
$slides = [];
if ($current_content && $current_content['content_type'] === 'slideshow') {
    // Check if it's a multi-image layout stored as JSON
    $data = json_decode($current_content['content_data'], true);
    if ($data && isset($data['type']) && $data['type'] !== 'slideshow') {
        // This is a multi-image layout
        $current_content['layout_data'] = $data;
    } else {
        // Traditional slideshow - get from content_slides table
        $stmt = $pdo->prepare("SELECT * FROM content_slides WHERE content_id = ? ORDER BY slide_order ASC");
        $stmt->execute([$current_content['id']]);
        $slides = $stmt->fetchAll();

        // If slides are empty, try to parse as JSON from content_data
        if (empty($slides) && $current_content['content_data']) {
            $data = json_decode($current_content['content_data'], true);
            if ($data && isset($data['type']) && $data['type'] === 'slideshow' && isset($data['images'])) {
                $slides = $data['images'];
            }
        }
    }
}

if (!$current_content) {
    $current_content = [
        'id' => null,
        'content_type' => 'message',
        'content_data' => 'Welcome to LMT TV Display',
        'message_type' => 'memo',
        'display_duration' => 10,
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

        /* Slideshow Container */
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

        /* Video Container */
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

        /* PDF Container */
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

        /* Message Styles */
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
        let currentVersion = <?php echo $current_version; ?>;
        let currentTimeouts = [];
        let pollingInterval = null;

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
                    if (data.success && data.content) {
                        currentContent = data.content;
                        currentSlides = data.slides || [];
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
            console.log('Slides count:', currentSlides ? currentSlides.length : 0);

            if (!currentContent || !currentContent.content_type) {
                console.error('Invalid content structure');
                loadDefaultContent();
                return;
            }

            switch (currentContent.content_type) {
                case 'slideshow':
                    // Check if it's a multi-image layout
                    if (currentContent.layout_data && currentContent.layout_data.type && currentContent.layout_data.type !== 'slideshow') {
                        loadMultiImageLayout(currentContent.layout_data);
                    } else if (currentSlides && currentSlides.length > 0) {
                        loadSlideshow();
                    } else {
                        // Try to parse content_data for traditional slideshow
                        try {
                            const parsed = JSON.parse(currentContent.content_data);
                            if (parsed.images && parsed.images.length > 0) {
                                // Convert to slides format
                                currentSlides = parsed.images.map((img, idx) => ({
                                    image_path: img.path,
                                    duration: img.duration || 10
                                }));
                                loadSlideshow();
                                return;
                            }
                        } catch (e) {
                            console.log('Not JSON or invalid format');
                        }
                        loadMessage();
                    }
                    break;
                case 'youtube':
                    loadYouTube();
                    break;
                case 'message':
                    loadMessage();
                    break;
                case 'ppt':
                    loadPDF();
                    break;
                default:
                    loadMessage();
            }
        }

        function loadSlideshow() {
            const wrapper = document.getElementById('contentWrapper');

            if (!currentSlides || currentSlides.length === 0) {
                console.log('No slides found');
                loadMessage();
                return;
            }

            console.log('Loading slideshow with', currentSlides.length, 'slides');
            console.log('Slides data:', currentSlides);

            let html = '<div class="slideshow-container">';
            currentSlides.forEach((slide, index) => {
                let imagePath = slide.image_path;
                if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                    imagePath = '/' + imagePath;
                }

                html += `<img src="${imagePath}" 
                               class="slide-image" 
                               data-index="${index}" 
                               style="opacity: ${index === 0 ? 1 : 0};"
                               onerror="this.style.display='none'">`;
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

            // Schedule content expiry
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const expiryTimeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
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

            console.log('Loading multi-image layout:', layoutType, 'with', images.length, 'images');

            let html = '';

            if (layoutType === '4-image') {
                // 2x2 Grid layout
                html = `<div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; width: 100%; height: 100%; gap: 10px; padding: 10px; background: #000;">`;
                images.forEach(image => {
                    let imagePath = image.path;
                    if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                        imagePath = '/' + imagePath;
                    }
                    html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                <img src="${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'">
                            </div>`;
                });
                html += `</div>`;
            } else if (layoutType === '2-image' || layoutType === '3-image') {
                // Horizontal layout
                const gridTemplate = layoutType === '2-image' ? '1fr 1fr' : '1fr 1fr 1fr';
                html = `<div style="display: grid; grid-template-columns: ${gridTemplate}; width: 100%; height: 100%; gap: 10px; padding: 10px; background: #000;">`;
                images.forEach(image => {
                    let imagePath = image.path;
                    if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                        imagePath = '/' + imagePath;
                    }
                    html += `<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                <img src="${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'">
                            </div>`;
                });
                html += `</div>`;
            } else {
                // Default fallback
                html = `<div style="display: flex; width: 100%; height: 100%; gap: 10px; padding: 10px; background: #000;">`;
                images.forEach(image => {
                    let imagePath = image.path;
                    if (!imagePath.startsWith('/') && !imagePath.startsWith('http')) {
                        imagePath = '/' + imagePath;
                    }
                    html += `<div style="flex: 1; display: flex; align-items: center; justify-content: center;">
                                <img src="${imagePath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'">
                            </div>`;
                });
                html += `</div>`;
            }

            wrapper.innerHTML = html;

            // Schedule expiry
            if (currentContent.display_duration && currentContent.display_duration > 0) {
                const timeoutId = setTimeout(() => {
                    loadNextContent();
                }, currentContent.display_duration * 1000);
                currentTimeouts.push(timeoutId);
            }
        }

        function loadYouTube() {
            const wrapper = document.getElementById('contentWrapper');
            let videoUrl = currentContent.content_data;

            const videoId = extractYouTubeId(videoUrl);
            // Add autoplay and force reload parameters
            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&loop=1&playlist=${videoId}&controls=0&showinfo=0&rel=0&modestbranding=1&iv_load_policy=3&enablejsapi=1`;

            wrapper.innerHTML = `
                <div class="video-container">
                    <iframe id="youtubeFrame" src="${embedUrl}" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                    </iframe>
                </div>
            `;

            // Force reload to ensure autoplay works
            const iframe = document.getElementById('youtubeFrame');
            if (iframe) {
                iframe.src = embedUrl;
            }

            let playCount = 0;
            const totalDuration = currentContent.display_duration * currentContent.loop_count;

            const timeoutId = setTimeout(() => {
                playCount++;
                if (playCount < currentContent.loop_count) {
                    const iframeReload = document.getElementById('youtubeFrame');
                    if (iframeReload) {
                        iframeReload.src = embedUrl;
                    }
                    const newTimeoutId = setTimeout(arguments.callee, totalDuration * 1000);
                    currentTimeouts.push(newTimeoutId);
                } else {
                    loadNextContent();
                }
            }, totalDuration * 1000);
            currentTimeouts.push(timeoutId);
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

            const viewerUrl = `https://docs.google.com/viewer?url=${encodeURIComponent(window.location.origin + filePath)}&embedded=true`;

            wrapper.innerHTML = `
                <div class="pdf-container">
                    <iframe src="${viewerUrl}" class="pdf-iframe" allowfullscreen></iframe>
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
            const messageText = currentContent.content_data || 'Welcome to ET TV Display';

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
            console.log('Loading next content, next_content_id:', currentContent.next_content_id);

            if (currentContent.next_content_id && currentContent.next_content_id !== null && currentContent.next_content_id !== '') {
                const nextId = parseInt(currentContent.next_content_id);
                fetch(`get_content.php?id=${nextId}&t=${Date.now()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.content) {
                            currentContent = data.content;
                            currentSlides = data.slides || [];
                            // If it's YouTube, force a small delay to ensure autoplay works
                            if (data.content.content_type === 'youtube') {
                                setTimeout(() => loadContent(), 100);
                            } else {
                                loadContent();
                            }
                        } else {
                            loadDefaultContent();
                        }
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
            fetch(`get_content.php?mode=${currentMode}&default=true&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.content) {
                        currentContent = data.content;
                        currentSlides = data.slides || [];
                        loadContent();
                    }
                })
                .catch(error => console.error('Error loading default content:', error));
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
                fetch(`check_version.php?mode=${currentMode}&version=${currentVersion}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_update) {
                            currentVersion = data.new_version;
                            fetch(`get_content.php?mode=${currentMode}&t=${Date.now()}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.content) {
                                        currentContent = data.content;
                                        currentSlides = data.slides || [];
                                        // If it's YouTube, force reload for autoplay
                                        if (data.content.content_type === 'youtube') {
                                            setTimeout(() => loadContent(), 100);
                                        } else {
                                            loadContent();
                                        }
                                    }
                                });
                        }
                    })
                    .catch(error => console.error('Polling error:', error));
            }, 3000);
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