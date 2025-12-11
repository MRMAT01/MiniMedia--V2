<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

$mediaId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'media';
$short = $_GET['short'] ?? '';

if (!$mediaId && !$short) {
    die("Invalid request");
}

// Fetch media info
if ($type === 'music') {
    $stmt = $pdo->prepare("SELECT * FROM music WHERE id = ?");
    $stmt->execute([$mediaId]);
} elseif ($type === 'tv') {
    // For TV, we usually download specific episodes
    // If short is provided, find by short_url
    if ($short) {
        $stmt = $pdo->prepare("SELECT * FROM media WHERE short_url = ? AND type = 'tv'");
        $stmt->execute([$short]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? AND type = 'tv'");
        $stmt->execute([$mediaId]);
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? AND type IN ('movie', 'featured')");
    $stmt->execute([$mediaId]);
}

$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    die("Media not found");
}

$filePath = $media['path'];

// Security check: ensure file is within allowed directories
$realPath = realpath($filePath);
if (!$realPath || !file_exists($realPath)) {
    // Try relative path if absolute fails
    $realPath = realpath(__DIR__ . '/' . $filePath);
    if (!$realPath || !file_exists($realPath)) {
        die("File not found on server");
    }
}

// Basic MIME type detection
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp4' => 'video/mp4',
    'mkv' => 'video/x-matroska',
    'avi' => 'video/x-msvideo',
    'mp3' => 'audio/mpeg',
    'flac' => 'audio/flac',
    'jpg' => 'image/jpeg',
    'png' => 'image/png'
];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Serve file
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($realPath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($realPath));

// Clear output buffer
if (ob_get_level()) ob_end_clean();

readfile($realPath);
exit;
?>
