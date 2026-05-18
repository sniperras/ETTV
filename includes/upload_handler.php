<?php
// includes/upload_handler.php - Updated with audio support
function validateAndUploadFile($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'mp4', 'pdf', 'mp3', 'wav', 'ogg', 'm4a'])
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error code: ' . $file['error']);
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Set max size based on file type
    if (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'])) {
        $max_size = 50 * 1024 * 1024; // 50MB for audio
    } elseif ($ext === 'mp4') {
        $max_size = 100 * 1024 * 1024; // 100MB for videos
    } elseif ($ext === 'pdf') {
        $max_size = 50 * 1024 * 1024; // 50MB for PDFs
    } else {
        $max_size = 10 * 1024 * 1024; // 10MB for images
    }

    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Max ' . ($max_size / 1024 / 1024) . 'MB allowed.');
    }

    // Validate file extension
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }

    // Validate file content based on type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4'
    ];

    // Special handling for PDF
    if ($ext === 'pdf') {
        $handle = fopen($file['tmp_name'], 'r');
        $first_bytes = fread($handle, 4);
        fclose($handle);

        if (substr($first_bytes, 0, 4) !== '%PDF') {
            throw new Exception('Invalid PDF file. File does not appear to be a valid PDF.');
        }
    }
    // For MP4
    elseif ($ext === 'mp4') {
        $handle = fopen($file['tmp_name'], 'r');
        $bytes = fread($handle, 8);
        fclose($handle);

        if (strpos($bytes, 'ftyp') === false && strpos($bytes, 'moov') === false && strpos($bytes, 'mdat') === false) {
            error_log('Warning: Uploaded file may not be a valid MP4: ' . $file['name']);
        }
    }
    // For images
    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
        if (isset($allowed_mimes[$ext]) && $allowed_mimes[$ext] !== $mime_type) {
            throw new Exception('Invalid file content. File type does not match extension.');
        }
    }
    // For audio files, just check mime type
    elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'])) {
        if (!in_array($mime_type, ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'])) {
            error_log('Warning: Uploaded audio file may have incorrect mime type: ' . $mime_type);
        }
    }

    // Generate secure filename
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    // Ensure directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    // Return the correct path based on file type
    if ($ext === 'mp4') {
        return 'uploads/videos/' . $filename;
    } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'])) {
        return 'uploads/audio/' . $filename;
    }

    return 'uploads/' . $filename;
}
