<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'config.php';

// --- Security ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// --- Lookup media ---
$short = $_GET['s'] ?? '';
if (!$short) exit;

$stmt = $pdo->prepare("SELECT path FROM media WHERE short_url = ?");
$stmt->execute([$short]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $stmt = $pdo->prepare("SELECT path FROM music WHERE short_url = ?");
    $stmt->execute([$short]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) exit;

$file = realpath(__DIR__ . '/' . $row['path']);
if (!$file || !file_exists($file)) exit;

// --- Helpers ---
function clean_buffers() {
    while (ob_get_level()) ob_end_clean();
}

function direct_stream($file, $mime) {
    clean_buffers();

    $size = filesize($file);
    $start = 0;
    $end = $size - 1;

    if (isset($_SERVER['HTTP_RANGE']) &&
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = intval($m[1]);
        if ($m[2] !== '') $end = intval($m[2]);
    }

    $length = $end - $start + 1;

    header("Content-Type: $mime");
    header("Accept-Ranges: bytes");
    header("Content-Length: $length");

    if ($start > 0) {
        header("HTTP/1.1 206 Partial Content");
        header("Content-Range: bytes $start-$end/$size");
    }

    $fp = fopen($file, 'rb');
    fseek($fp, $start);

    $buffer = 1024 * 64;
    while (!feof($fp) && $length > 0) {
        $read = min($length, $buffer);
        echo fread($fp, $read);
        flush();
        $length -= $read;
    }

    fclose($fp);
    exit;
}

// --- MIME check ---
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$is_mp4 = in_array($ext, ['mp4', 'm4v']);

// --- Direct play for MP4 ---
if ($is_mp4) {
    direct_stream($file, 'video/mp4');
    exit;
}

// --- FFmpeg fallback ---
clean_buffers();
header("Content-Type: video/mp4");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

$log = __DIR__ . "/logs/ffmpeg_stream_errors.log";

$cmd =
    escapeshellarg(FFMPEG_PATH) .
    " -hide_banner -loglevel error -fflags +genpts " .
    " -i " . escapeshellarg($file) .
    " -map 0:v:0 -map 0:a:0? " .
    " -c:v libx264 -preset veryfast -crf 23 " .
    " -c:a aac -ac 2 -b:a 128k " .
    " -vsync cfr " .
    " -max_muxing_queue_size 1024 " .
    " -movflags +frag_keyframe+empty_moov+faststart " .
    " -f mp4 pipe:1 2>>" . escapeshellarg($log);

passthru($cmd);
exit;