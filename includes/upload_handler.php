<?php
// includes/upload_handler.php
function validateAndUploadFile($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp'])
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large. Max 10MB allowed.');
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp'
    ];

    if ($allowed_mimes[$ext] !== $mime_type) {
        throw new Exception('Invalid file content. File type does not match extension.');
    }

    // Generate secure filename
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    return 'uploads/' . $filename;
}
