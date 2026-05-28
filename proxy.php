<?php
// proxy.php - Simple PHP proxy to fetch content
header('Content-Type: text/html');

$url = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($url)) {
    die('No URL provided');
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    die('Invalid URL');
}

// Fetch the content
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response) {
    // Remove X-Frame-Options headers that prevent embedding
    $response = preg_replace('/<meta[^>]*(X-Frame-Options|frame-ancestors)[^>]*>/i', '', $response);
    
    // Also remove frame-ancestors CSP headers
    $response = preg_replace('/Content-Security-Policy[^>]*frame-ancestors[^>]*;/i', '', $response);
    
    // Add base tag for relative URLs
    $base_tag = '<base href="' . $url . '">';
    $response = preg_replace('/<head([^>]*)>/i', '<head$1>' . $base_tag, $response);
    
    // Ensure viewport is set for proper scrolling
    if (strpos($response, 'viewport') === false) {
        $viewport_meta = '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">';
        $response = preg_replace('/<head([^>]*)>/i', '<head$1>' . $viewport_meta, $response);
    }
    
    echo $response;
} else {
    echo '<!DOCTYPE html><html><head><title>Error</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:Arial;text-align:center;padding:50px;}</style>';
    echo '</head><body>';
    echo '<h1>Unable to load website</h1>';
    echo '<p>The website could not be loaded. <a href="' . htmlspecialchars($url) . '" target="_blank">Click here to open directly</a></p>';
    echo '</body></html>';
}
?>