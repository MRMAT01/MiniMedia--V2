<?php
/**
 * File Upload Validation
 * Validates uploaded files for type, size, and security
 */

function validateUploadedFile($file, $type = 'video') {
    // Define size limits
    $maxSizes = [
        'video' => 5 * 1024 * 1024 * 1024, // 5GB
        'audio' => 500 * 1024 * 1024,       // 500MB
        'image' => 10 * 1024 * 1024          // 10MB
    ];
    
    // Define allowed MIME types
    $allowedMimes = [
        'video' => [
            'video/mp4', 
            'video/x-matroska', 
            'video/x-msvideo',  // avi
            'video/quicktime',   // mov
            'video/avi'
        ],
        'audio' => [
            'audio/mpeg',        // mp3
            'audio/mp3',
            'audio/ogg',
            'audio/wav',
            'audio/x-wav'
        ],
        'image' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/jpg'
        ]
    ];
    
    // Check if file exists
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        return ['valid' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $maxSizes[$type]) {
        $maxSizeMB = round($maxSizes[$type] / 1024 / 1024);
        return ['valid' => false, 'error' => "File too large (max {$maxSizeMB}MB)"];
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimes[$type])) {
        return ['valid' => false, 'error' => "Invalid file type. Expected {$type}, got {$mimeType}"];
    }
    
    // Additional security: check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = [
        'video' => ['mp4', 'mkv', 'avi', 'mov', 'flv', 'wmv', 'ts', 'm4v'],
        'audio' => ['mp3', 'ogg', 'wav', 'm4a'],
        'image' => ['jpg', 'jpeg', 'png', 'webp']
    ];
    
    if (!in_array($extension, $allowedExtensions[$type])) {
        return ['valid' => false, 'error' => "Invalid file extension: {$extension}"];
    }
    
    return ['valid' => true];
}

/**
 * Sanitize uploaded filename
 */
function sanitizeUploadFilename($filename) {
    // Remove extension
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // Remove special characters
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_');
    
    // Rebuild filename
    return $name . '.' . strtolower($ext);
}
