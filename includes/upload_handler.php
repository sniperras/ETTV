<?php
// includes/upload_handler.php
function validateAndUploadFile($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp']) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error code: ' . $file['error']);
    }
    
    // Check file size (max 50MB for PDFs, 10MB for images)
    $max_size = in_array('pdf', $allowed_types) ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Max ' . ($max_size / 1024 / 1024) . 'MB allowed.');
    }
    
    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }
    
    // For PDFs, skip MIME type validation (more permissive)
    if ($ext === 'pdf') {
        // Just check if it's a valid PDF (first few bytes)
        $handle = fopen($file['tmp_name'], 'r');
        $first_bytes = fread($handle, 4);
        fclose($handle);
        
        if (substr($first_bytes, 0, 4) !== '%PDF') {
            throw new Exception('Invalid PDF file. File does not appear to be a valid PDF.');
        }
    } else {
        // Verify MIME type for images
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
    
    return 'uploads/' . $filename;
}
?>