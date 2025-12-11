<?php
// ================================================
// MiniMediaServer - Image Router (Cover/Backdrop)
// Portable & Safe Version
// ================================================
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . 'logs/php_error.txt');

require 'config.php';

$short = $_GET['s'] ?? '';
$type  = $_GET['type'] ?? 'cover';

if (!$short) {
    http_response_code(400);
    exit;
}

// Fetch image paths from DB
$stmt = $pdo->prepare("SELECT cover, backdrop FROM media WHERE short_url = ?");
$stmt->execute([$short]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit;
}

// Choose requested file
$relativePath = ($type === 'backdrop') ? ($row['backdrop'] ?? '') : ($row['cover'] ?? '');
$relativePath = trim($relativePath, "/\\");
$relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);

// Build local absolute path (portable)
$file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

// Debug logging
//file_put_contents(__DIR__ . '/image_debug.txt', "Request: s=$short type=$type\nDB Path: $relativePath\nFull Path: $file\nExists: " . (file_exists($file) ? 'YES' : 'NO') . "\n\n", FILE_APPEND);

// Fallback image (always exists)
$fallback = __DIR__ . DIRECTORY_SEPARATOR . 'media_cache' . DIRECTORY_SEPARATOR .
    ($type === 'backdrop' ? 'no_backdrop.png' : 'nocover.png');

// Use fallback if missing or invalid
if (!$relativePath || !file_exists($file) || !is_file($file)) {
    $file = $fallback;
}

// Prevent any accidental output before image
if (ob_get_length()) ob_end_clean();

// Detect mime type safely
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream'
};

// Serve image headers
header("Content-Type: $mime");
header("Cache-Control: public, max-age=86400");

// Read image file
$fp = fopen($file, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
}
exit;
?>