<?php
// Absolutely no whitespace or BOM above this line!
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require 'config.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$short = $_GET['s'] ?? '';
if (!$short) { http_response_code(400); exit; }

// Check short_url first
$stmt = $pdo->prepare("SELECT path, title FROM media WHERE short_url = ? LIMIT 1");
$stmt->execute([$short]);
$track = $stmt->fetch(PDO::FETCH_ASSOC);

// If not found, try by path
if (!$track) {
    $stmt = $pdo->prepare("SELECT path, title FROM media WHERE path = ? LIMIT 1");
    $stmt->execute([$short]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$track) { http_response_code(404); exit; }

// Resolve file path
$file = realpath(__DIR__ . '/' . $track['path']);
if (!$file || !file_exists($file)) {
    http_response_code(404);
    exit;
}

// Detect MIME type
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match($ext) {
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    default => 'application/octet-stream'
};

// Clean output buffer to prevent corruption
if (ob_get_level()) {
    ob_end_clean();
}

// Stream headers
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

$size = filesize($file);
$start = 0;
$end = $size - 1;

// Partial content support
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
    $start = intval($matches[1]);
    if ($matches[2] !== '') $end = intval($matches[2]);
    header('HTTP/1.1 206 Partial Content');
}

$length = $end - $start + 1;

header("Content-Length: $length");
header("Content-Range: bytes $start-$end/$size");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Connection: close');

// Open file and stream
$fp = fopen($file, 'rb');
fseek($fp, $start);
$chunk = 8192;

while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, $chunk);
    flush();
}
fclose($fp);
exit;