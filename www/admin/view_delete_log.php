<?php
// ================================================
// MiniMediaServer - Delete Log Viewer
// Secure read-only display of delete_log.txt + php_error.txt
// ================================================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Admin check ---
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role, username, profile_image FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$usr = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$usr || $usr['role']!=='admin'){
    echo "<h1 style='color:#fff;text-align:center;margin-top:20%'>Access denied</h1>";
    exit;
}
$profileImage = (!empty($usr['profile_image']) && file_exists('../'.$usr['profile_image'])) ? '../'.$usr['profile_image'] : "../profile/guest.png";

// --- Path helper for correct slashes (cross-platform) ---
function mediaPath($file) {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . $file);
}

// --- Define log files ---
$logFile    = mediaPath('../logs/log.txt');
$backupFile = mediaPath('../logs/delete_log.old.txt');
$phpFile    = mediaPath('../logs/php_error.txt');
$ffmpegFile    = mediaPath('../logs/ffmpeg_stream_errors.log');
// --- Function to read last N lines safely ---
function tailFile($file, $lines = 200) {
    if (!file_exists($file)) return "[No log file found: $file]";
    $data = @file($file, FILE_IGNORE_NEW_LINES);
    if (!$data) return "[Empty or unreadable file: $file]";
    $data = array_slice($data, -$lines);
    return implode("\n", $data);
}

// --- Load logs ---
$currentLog = tailFile($logFile, 300);
$backupLog  = tailFile($backupFile, 50);
$phpLog     = tailFile($phpFile, 300);
$ffmpegLog     = tailFile($ffmpegFile, 300);

// Process log actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete_current':
            if (file_exists($logFile)) unlink($logFile);
            $currentLog = "[Delete log deleted]";
            break;

        case 'delete_php':
            if (file_exists($phpFile)) unlink($phpFile);
            $phpLog = "[PHP error log deleted]";
            break;

        case 'backup_log':
            if (file_exists($logFile)) copy($logFile, $backupFile);
            $backupLog = tailFile($backupFile, 50);
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - View Logs</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
body {margin:0; font-family:sans-serif; background:#121212; color:#fff;}
#sidebar {position:fixed; top:0; left:0; bottom:0; width:250px; background:#1f1f1f; overflow-y:auto; padding:0; z-index:100;}
#sidebar .logo, #sidebar .profile {width:100%; text-align:center; padding:20px; border-bottom:1px solid #333;}
#sidebar .logo img {width:60%; max-width:100px; border-radius:8px; display:block; margin:0 auto 10px;}
#sidebar .logo span {display:block; font-size:1.2em; font-weight:bold;}
#sidebar .profile img {width:60px; height:60px; border-radius:50%; margin-bottom:5px;}
#sidebar .profile span {display:block; color:#ccc;}
#sidebar .btn-sidebar {width:100%; text-align:left; border:none; background:none; color:#ddd; padding:10px 20px; margin:0; border-bottom:1px solid #333;}
#sidebar .btn-sidebar:hover {background:#333; color:#fff;}
header {position:fixed; left:250px; right:0; top:0; height:60px; background:#222; display:flex; justify-content:space-between; align-items:center; padding:0 20px; transition:0.3s; z-index:90;}
#sideMenu.collapsed ~ header {left:60px;}
main {margin-left:250px; padding:80px 30px 30px 30px;}
.dashboard-tile {padding:20px; border-radius:12px; text-align:center; background:#222; transition:transform .2s; cursor:pointer;}
.dashboard-tile:hover {transform:translateY(-4px); background:#333;}
.media-grid {display:flex; flex-wrap:wrap; gap:15px;}
.media-card {width:160px; text-align:center; border-radius:10px; overflow:hidden; background:#222; transition:transform .2s;}
.media-card:hover {transform:scale(1.05);}
.media-card img {width:100%; height:220px; object-fit:cover; border-bottom:1px solid #444;}
.media-card span {display:block; margin-top:5px; color:#ccc;}
.navbar-custom {background:#222; backdrop-filter: blur(5px);}
pre {
    background: #1e1e1e;
    border: 1px solid #333;
    padding: 15px;
    overflow-y: auto;
    max-height: 80vh;
}
h1, h2 {
    color: #ffb74d;
}

footer{background:#000;text-align:center;padding:1rem;margin-top:auto;}
</style>
</head>
<body>
<!-- Sidebar -->
<div id="sidebar">
  <div class="logo">
    <a href="../index.php"><img src="<?= getLogoPath() ?>" alt="MiniMedia"></a></div>
  <div class="profile">
    <div class="ms-auto dropdown">
    <a class="nav-link dropdown-toggle d-flex align-items-center text-light" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
      <div class="rounded-circle overflow-hidden me-2" style="width:40px; height:40px;">
        <img src="<?=htmlspecialchars($profileImage)?>" class="w-100 h-100" style="object-fit:cover;">
      </div>
      <?=htmlspecialchars($usr['username'])?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
      <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user"></i> Profile</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
    </ul>
  </div>
  </div>
  <button class="btn-sidebar" onclick="window.location='index.php?page=dashboard'"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</button>
  <button class="btn-sidebar" onclick="window.location='index.php?page=movies'"><i class="fas fa-film me-2"></i>Movies</button>
  <button class="btn-sidebar" onclick="window.location='index.php?page=tv'"><i class="fas fa-tv me-2"></i>TV Shows</button>
  <button class="btn-sidebar" onclick="window.location='index.php?page=music'"><i class="fas fa-music me-2"></i>Music</button>
  <button class="btn-sidebar" onclick="window.location='index.php?page=featured'"><i class="fas fa-eye me-2"></i>Featured</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='admin_media.php'"><i class="fas fa-folder me-2"></i>Manage Media</button>
  <button class="btn-sidebar" onclick="window.location='music_manager.php'"><i class="fas fa-folder me-2"></i>Manage Music</button>
  <button class="btn-sidebar" onclick="window.location='my_media.php'"><i class="fas fa-sync me-2"></i>Rescan Media Images</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='admin_scan.php'"><i class="fas fa-sync me-2"></i>Bulk Scan</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='admin_categories.php'"><i class="fas fa-list me-2"></i>Manage Categories</button>
  <button class="btn-sidebar" onclick="window.location='admin_genre.php'"><i class="fas fa-tags me-2"></i>Manage Genre</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='view_delete_log.php'"><i class="fas fa-file-alt me-2"></i>View/Delete Logs</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='admin_index_images.php'"><i class="fas fa-image me-2"></i>Change Index Images</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='users.php'"><i class="fas fa-users me-2"></i>Users</button>
</div>
<header>
  <h4>Admin Dashboard</h4>
  <div class="d-flex align-items-center gap-3">
  </div>
</header>
<!-- Main Content -->
<main>
<h1>🧾 MiniMediaServer Log Viewer</h1>
<form method="post" class="mb-3 d-flex gap-2 flex-wrap">
    <button type="submit" name="action" value="delete_current" class="btn btn-danger btn-sm"
        onclick="return confirm('Are you sure you want to delete the current delete log?')">
        🗑️ Delete Current Log
    </button>

    <button type="submit" name="action" value="delete_php" class="btn btn-danger btn-sm"
        onclick="return confirm('Are you sure you want to delete the PHP error log?')">
        🗑️ Delete PHP Error Log
    </button>

    <button type="submit" name="action" value="backup_log" class="btn btn-warning btn-sm"
        onclick="return confirm('Backup current delete log?')">
        💾 Backup Current Log
    </button>
</form>

<p><a href="view_delete_log.php">🔄 Refresh</a></p>

<h2>Current Delete Log</h2>
<pre><?= htmlspecialchars($currentLog) ?></pre>

<?php if ($backupLog && strlen(trim($backupLog)) > 0): ?>
<h2>Previous Delete Log (Rotated)</h2>
<pre><?= htmlspecialchars($backupLog) ?></pre>
<?php endif; ?>

<h2>⚠️ PHP Error Log</h2>
<pre><?= htmlspecialchars($phpLog) ?></pre>

<h2>⚠️ FFMPEG Error Log</h2>
<pre><?= htmlspecialchars($ffmpegLog) ?></pre>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="index.php">MrMat</a></p>
</footer>
</body>
</html>