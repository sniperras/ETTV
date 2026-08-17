<?php

function getPdfPathFromContentData($content_data)
{
    $pdfPath = $content_data;
    $data = json_decode($content_data, true);
    if ($data && isset($data['file_path'])) {
        $pdfPath = $data['file_path'];
    }

    return $pdfPath;
}

function resolvePdfFileOnDisk($pdfPath)
{
    $pdfPath = str_replace('\\', '/', (string) $pdfPath);
    $pdfPath = preg_replace('#^/?uploads/uploads/#', 'uploads/', $pdfPath);
    $pdfPath = ltrim($pdfPath, '/');
    $filename = basename($pdfPath);

    $possible_paths = [
        __DIR__ . '/../' . $pdfPath,
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/' . $pdfPath : null),
        __DIR__ . '/../uploads/pdf/' . $filename,
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/uploads/pdf/' . $filename : null),
        __DIR__ . '/../uploads/' . $filename,
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $filename : null),
        '/home/vol*/if0_*/htdocs/' . $pdfPath,
        '/home/vol*/if0_*/htdocs/uploads/pdf/' . $filename,
        '/home/vol*/if0_*/htdocs/uploads/' . $filename,
    ];

    foreach ($possible_paths as $path) {
        if (!$path) {
            continue;
        }

        $globbed = glob($path);
        if ($globbed && !empty($globbed) && file_exists($globbed[0])) {
            return $globbed[0];
        }

        if (file_exists($path)) {
            return $path;
        }
    }

    return null;
}

function servePdfFile($filePath)
{
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit();
}
