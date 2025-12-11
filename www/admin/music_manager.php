<?php
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

// --- Normalize title helper ---
function normalizeTitleForFolder($title, $keepDash = true) {
    $baseName = preg_replace('/\.(mp3|wav|flac|m4a|aac|ogg)$/i', '', $title);
    $clean = preg_replace('/(\[.*?\]|\(.*?\)|\b(5|5.1|[0-9]{3,4}p|WEB-?DL|WEBRip|BluRay|BRRip|DVDRip|HDTV|x264|x265|AAC|AC3)\b)/i','',$baseName);
    if ($keepDash) {
        $clean = trim(preg_replace('/[^\p{L}\p{N}\-\s]+/u', '', $clean));
    } else {
        $clean = trim(preg_replace('/[\[\]\(\)\._-]+/', ' ', $clean));
    }
    return trim(preg_replace('/\s+/', ' ', $clean));
}

// --- Find music cover dynamically ---
function getMusicCover($title) {
    $folderName = normalizeTitleForFolder($title, true);
    $folderPath = __DIR__ . "/../media_cache/{$folderName}/cover.jpg";
    if(file_exists($folderPath)) return "../media_cache/{$folderName}/cover.jpg";
    return "../images/noimage.png";
}

// --- Handle delete action ---
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT path, title FROM media WHERE id=? AND type='music'");
    $stmt->execute([$id]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if($track){
        // --- Delete the actual music file ---
        $musicPath = $track['path'];
        if(!preg_match('#^(?:[a-z]:)?/#i', $musicPath)){
            $musicPath = __DIR__ . '/../' . ltrim($musicPath, '/');
        }
        if(file_exists($musicPath)){
            unlink($musicPath);
        }

        // --- Delete cover and backdrop in media_cache ---
        $cacheFolder = __DIR__ . "/../media_cache/" . normalizeTitleForFolder($track['title'], true);

        $coverPath = $cacheFolder . "/cover.jpg";
        if(file_exists($coverPath)){
            unlink($coverPath);
        }

        $backdropPath = $cacheFolder . "/backdrop.jpg"; // Add this line
        if(file_exists($backdropPath)){
            unlink($backdropPath);
        }

        // Optionally remove the folder if empty
        if(is_dir($cacheFolder) && count(scandir($cacheFolder)) === 2){
            rmdir($cacheFolder);
        }

        // --- Delete DB record ---
        $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
    }

    header("Location: music_manager.php");
    exit;
}

// --- Filters ---
$search = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT * FROM media WHERE type='music'";
if($search){
    $sql .= " AND (title LIKE ? OR artist LIKE ? OR album LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Manage Music</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {height:100%; margin:0; background:url('../images/bg.jpg') fixed no-repeat; background-size:cover; color:#fff;}
body {display:flex; flex-direction:column;}
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
.table-dark td,.table-dark th{vertical-align:middle;}
.media-cover{width:80px;height:80px;object-fit:cover;border-radius:6px;}
/* Layout spacing */
.page-content {
    padding-top: 90px; /* distance below fixed navbar */
    padding-bottom: 60px; 
    min-height: calc(100vh - 160px);
}

/* Footer */
footer {
    background: #000;
    color: #ccc;
    text-align: center;
    padding: 1rem 0;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 20px; 
}
.modal-backdrop {
  z-index: 1040 !important;
}
.modal {
  z-index: 1050 !important;
}
.modal-content {
  position: relative;
  z-index: 1060 !important;
}
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
</header>
<main>
<div class="card bg-dark text-light p-4 shadow">
<h2>🎵 Music Manager</h2>
<form method="get" class="d-flex gap-2 mb-3 flex-wrap">
<input type="text" name="q" placeholder="Search by title, artist, album" class="form-control" value="<?=htmlspecialchars($search)?>">
<button type="submit" class="btn btn-primary">Search</button>
<a href="music_manager.php" class="btn btn-secondary">Reset</a>
</form>

<div class="table-responsive">
<table class="table table-dark table-striped table-bordered align-middle">
<thead class="table-secondary text-dark">
<tr>
<th>ID</th><th>Cover</th><th>Title</th><th>Artist</th><th>Album</th><th>Play</th><th>Added</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($tracks): foreach($tracks as $t): ?>
<tr>
<td><?= $t['id'] ?></td>
<td><img src="<?=htmlspecialchars(getMusicCover($t['title']))?>" class="media-cover" alt="cover"></td>
<td><?= htmlspecialchars($t['title'] ?? '') ?></td>
<td><?= htmlspecialchars($t['artist'] ?? '') ?></td>
<td><?= htmlspecialchars($t['album'] ?? '') ?></td>
<td>
<?php
$streamUrl = "../stream_music.php?s=" . urlencode($t['short_url'] ?? $t['path']);
?>
<button class="btn btn-sm btn-success"
onclick="playTrack('<?=htmlspecialchars($streamUrl)?>','<?=htmlspecialchars(addslashes($t['title'] ?? ''))?>')">▶️ Play</button>
</td>
<td><?= $t['created_at'] ?></td>
<td>
<a href="music_edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning mb-1"><i class="fas fa-edit"></i></a>
<a href="music_manager.php?delete_id=<?= $t['id'] ?>" onclick="return confirm('Delete this track?')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="8" class="text-center text-warning">No tracks found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- Music Player Modal -->
<div class="modal fade" id="playerModal" tabindex="-1" aria-labelledby="playerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="playerModalLabel">Music Player</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <strong id="nowPlaying" class="fs-5">Now Playing:</strong>
        <audio id="audioPlayer" controls style="width:100%; max-width:400px;"></audio>
      </div>
    </div>
  </div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function playTrack(url, title){
  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
  const audio = document.getElementById('audioPlayer');
  const nowPlaying = document.getElementById('nowPlaying');
  nowPlaying.innerText = "Now Playing: " + title;
  audio.src = url;
  audio.volume = 0.2;
  audio.play().catch(err => console.error(err));

  const modal = new bootstrap.Modal(document.getElementById('playerModal'));
  modal.show();

  document.getElementById('playerModal').addEventListener('hidden.bs.modal', () => {
    audio.pause();
    audio.currentTime = 0;
  });
}
</script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="index.php">MrMat</a></p>
</footer>
</body>
</html>