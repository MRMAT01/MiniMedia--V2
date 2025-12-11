<?php
$dbPath = __DIR__ . '/database/mmedia.sqlite';
@mkdir(__DIR__ . '/database', 0777, true);

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA foreign_keys = ON");
} catch (PDOException $e) {
    die("SQLite connection failed: " . $e->getMessage());
}

// Always base paths on the script directory
$baseDir = __DIR__;
// Folders location
define('MEDIA_IMPORT_DIR', __DIR__ . '/import');   // Source folder
define('MEDIA_MOVIES_DIR', __DIR__ . '/movies');
define('MEDIA_TV_DIR', __DIR__ . '/tv');
define('MEDIA_MUSIC_DIR', __DIR__ . '/music');
define('MEDIA_FEATURED_DIR', __DIR__ . '/featured');
define('MEDIA_CACHE_DIR', __DIR__ . '/media_cache');
// ============================================
// External Tools Configuration (Portable)
// ============================================

// FFmpeg configuration - auto-detect or use configured path
function detectFFmpegPath() {
    $possiblePaths = [
        __DIR__ . '/ffmpeg/ffmpeg.exe',           // In www/ffmpeg/ (primary location)
        __DIR__ . '/../ffmpeg/ffmpeg.exe',        // Legacy location (one level up)
        'ffmpeg',                                  // System PATH
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Check if ffmpeg is in system PATH
    $output = [];
    $returnVar = 0;
    @exec('ffmpeg -version 2>&1', $output, $returnVar);
    if ($returnVar === 0) {
        return 'ffmpeg';
    }
    
    return null;
}

// getID3 library configuration
function detectGetID3Path() {
    $possiblePaths = [
        __DIR__ . '/getid3/getid3.php',           // In www/getid3/ (primary location)
        __DIR__ . '/../getid3/getid3.php',        // Legacy location (one level up)
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

define('FFMPEG_PATH', detectFFmpegPath());
define('GETID3_PATH', detectGetID3Path());

// Helper functions to check tool availability
function isFFmpegAvailable() {
    return FFMPEG_PATH !== null;
}

function isGetID3Available() {
    return GETID3_PATH !== null;
}

// TMDb API (v3 key)
$tmdb_api = '329f15b1739db617dec4f486b4df88d9'; // Add your TMDb API key here
// TMDb Read Access Token (v4)
$tmdb_token = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiIzMjlmMTViMTczOWRiNjE3ZGVjNGY0ODZiNGRmODhkOSIsIm5iZiI6MTc1NjE2Njc1Ny42ODUsInN1YiI6IjY4YWNmYTY1YzQ0NTg3NGU2Nzc4MjQ3MCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.lQDqbqthgp5SZrBifP5t2IA_kv29M5XF5HdIHptI7KE';

// Logo configuration
define('LOGO_FILENAME', 'logo.png');
define('LOGO_DIR', 'images');

// Helper function to get logo path (handles subdirectories)
function getLogoPath() {
    $currentDir = dirname($_SERVER['PHP_SELF']);
    // Normalize path separators to forward slashes
    $currentDir = str_replace('\\', '/', $currentDir);
    // Remove leading/trailing slashes and split into segments
    $segments = array_filter(explode('/', trim($currentDir, '/')));
    $depth = count($segments);
    $prefix = str_repeat('../', $depth);
    return $prefix . LOGO_DIR . '/' . LOGO_FILENAME;
}

// Legacy variable for backward compatibility
$logo = LOGO_DIR . '/' . LOGO_FILENAME;

// ============================================
// Security Enhancements
// ============================================

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
    
    // Session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
            header('Location: login.php');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID on first access
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

// CSRF Protection Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// Input validation helpers
function validateMediaType($type) {
    $allowed = ['movie', 'tv', 'music', 'featured'];
    return in_array($type, $allowed) ? $type : 'movie';
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    return trim($filename, '_');
}

// Error logging wrapper
function logError($message, $context = []) {
    $logFile = __DIR__ . '/logs/app_error.log';
    @mkdir(dirname($logFile), 0777, true);
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = json_encode($context);
    file_put_contents($logFile, "[$timestamp] $message | Context: $contextStr\n", FILE_APPEND);
}

?>

