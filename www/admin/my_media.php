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

// --- Safe escaping helper ---
function h($value): string {
    if (!isset($value) || !is_scalar($value)) return '';
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --- Profile image ---
$profileImage = (!empty($usr['profile_image']) && file_exists('../'.$usr['profile_image'])) ? '../'.$usr['profile_image'] : "../profile/guest.png";

// --- Normalize title to folder, keeping dashes ---
function normalizeTitleForFolder($title, $keepDash=false) {
    $base = preg_replace('/\.(mp4|mkv|avi|mov|flv|mp3|wav|ogg|flac|m4a|aac)$/i', '', $title);
    $base = preg_replace('/(\[.*?\]|\(.*?\)|\b(5|5.1|4k|2160p|1080p|720p|480p|HDR|HDR10|DV|DDP5|WEB-?DL|WEBRip|WEB|BluRay|BRRip|BDRip|DVDRip|HDTV|HDTS|CAM|TS|TC|x264|x265|h264|hevc|10bit|[0-9]{1,4}MB|[0-9]{1,2}GB|NF|AMZN|HULU|MAX|UHD|HDRip|PROPER|REPACK|LIMITED|EXTENDED|UNRATED|YTS|RARBG|RGB|EVO|P2P|AAC|AC5|Rapta|BONE|RMTeam|WORKPRINT|COLLECTiVE|sylix|MeGusta|EZTVx|to|FENiX|ETHEL|nhtfs|ELiTE|ATVP|NTb|successfulcrab|CRAV|SNAKE|SYNCOPY|JFF|AFG|FGT|FLEET|Galaxy(RG|TV)?|RIP|Ganool|YIFY|EVO|P2P|U2|NeoNoir|TGx|MX|AM|RARBG|ETRG|Eng|ESub|DD(5|1)?|LAMA|Teema|Sagrona|XviD)\b)/i', '', $base);
    $base = preg_replace('/S\d{2}E\d{2}/i', '', $base);

    if ($keepDash) {
        // Keep letters, numbers, spaces, and dashes
        $clean = preg_replace('/[^\p{L}\p{N}\-\s]+/u', '', $base);
    } else {
        // Replace unwanted chars with space
        $clean = preg_replace('/[\[\]\(\)\._]+/', ' ', $base);
    }

    // Collapse multiple spaces
    $clean = preg_replace('/\s+/', ' ', $clean);
    return trim($clean) ?: 'unknown';
}

// --- Find media folder ---
function findMediaFolder($title, $type) {
    $cacheDir = __DIR__ . '/../media_cache/';
    $folders = glob($cacheDir . '*', GLOB_ONLYDIR);
    $normalized = normalizeTitleForFolder($title, $type==='music');
    foreach ($folders as $f) {
        if (strcasecmp(basename($f), $normalized) === 0) return $f;
    }
    return $cacheDir . $normalized;
}

// --- Get dynamic cover ---
function getDynamicCover($title, $type) {
    $folder = findMediaFolder($title, $type);

    if ($type==='music' || $type==='movie') {
        $cover = $folder . '/cover.jpg';
        return file_exists($cover) ? "../media_cache/".basename($folder)."/cover.jpg" : "../media_cache/noimage.png";
    }

    if ($type==='tv') {
        // Search season folders first
        $seasons = glob($folder . '/*', GLOB_ONLYDIR);
        foreach ($seasons as $s) {
            $cover = $s . '/cover.jpg';
            if (file_exists($cover)) return "../media_cache/".basename($folder)."/".basename($s)."/cover.jpg";
        }
        // fallback
        $cover = $folder . '/cover.jpg';
        return file_exists($cover) ? "../media_cache/".basename($folder)."/cover.jpg" : "../media_cache/noimage.png";
    }

    return "../media_cache/noimage.png";
}

// --- Get dynamic backdrop ---
function getDynamicBackdrop($title, $type) {
    $folder = findMediaFolder($title, $type);

    if ($type==='music') return "../media_cache/no_backdrop.png";
    if ($type==='movie') {
        $backdrop = $folder . '/backdrop.jpg';
        return file_exists($backdrop) ? "../media_cache/".basename($folder)."/backdrop.jpg" : "../media_cache/no_backdrop.png";
    }

    if ($type==='tv') {
        $seasons = glob($folder . '/*', GLOB_ONLYDIR);
        foreach ($seasons as $s) {
            $backdrop = $s . '/backdrop.jpg';
            if (file_exists($backdrop)) return "../media_cache/".basename($folder)."/".basename($s)."/backdrop.jpg";
        }
        $backdrop = $folder . '/backdrop.jpg';
        return file_exists($backdrop) ? "../media_cache/".basename($folder)."/backdrop.jpg" : "../media_cache/no_backdrop.png";
    }

    return "../media_cache/no_backdrop.png";
}

function getBackdropPath($type, $title) {
    if ($type === 'music') return "../media_cache/no_backdrop.png";

    $folderName = findMediaFolder($title, $type);
    $folderPath = __DIR__ . "/../media_cache/{$folderName}";

    if ($type === 'movie') {
        $backdrop = $folderPath . '/backdrop.jpg';
        if (file_exists($backdrop)) return "../media_cache/{$folderName}/backdrop.jpg";
        return "../media_cache/no_backdrop.png";
    }

    if ($type === 'tv') {
        $seasonFolders = glob($folderPath . '/*', GLOB_ONLYDIR);
        foreach ($seasonFolders as $s) {
            $backdrop = $s . '/backdrop.jpg';
            if (file_exists($backdrop)) return "../media_cache/{$folderName}/" . basename($s) . "/backdrop.jpg";
        }
        $backdrop = $folderPath . '/backdrop.jpg';
        if (file_exists($backdrop)) return "../media_cache/{$folderName}/backdrop.jpg";
        return "../media_cache/no_backdrop.png";
    }

    return "../media_cache/no_backdrop.png";
}

// --- Handle POST actions ---
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id=?");
    $stmt->execute([$id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$media) exit('Media not found.');

    $title = trim($_POST['title'] ?? $media['title']);
    $type = $_POST['type'] ?? $media['type'];
    $season = $_POST['season'] ?: null;
    $episode = $_POST['episode'] ?: null;

    $cleanTitle = normalizeTitleForFolder($title, $type==='music');

    $mediaCacheFolder = __DIR__ . "/../media_cache/{$cleanTitle}";
    @mkdir($mediaCacheFolder, 0777, true);

    // Manual cover upload
    if(!empty($_FILES['manual_cover']['tmp_name'])){
        $coverPath = "media_cache/{$cleanTitle}/cover.jpg";
        if(!empty($media['cover']) && file_exists(__DIR__.'/../'.$media['cover']) && !str_contains($media['cover'], 'noimage.png')){
            @unlink(__DIR__.'/../'.$media['cover']);
        }
        move_uploaded_file($_FILES['manual_cover']['tmp_name'], __DIR__.'/../'.$coverPath);
        $stmt = $pdo->prepare("UPDATE media SET cover=? WHERE id=?");
        $stmt->execute([$coverPath, $id]);
    }

    // Manual backdrop upload
    if(!empty($_FILES['manual_backdrop']['tmp_name'])){
        $backdropPath = "media_cache/{$cleanTitle}/backdrop.jpg";
        if(!empty($media['backdrop']) && file_exists(__DIR__.'/../'.$media['backdrop']) && !str_contains($media['backdrop'], 'no_backdrop.png')){
            @unlink(__DIR__.'/../'.$media['backdrop']);
        }
        move_uploaded_file($_FILES['manual_backdrop']['tmp_name'], __DIR__.'/../'.$backdropPath);
        $stmt = $pdo->prepare("UPDATE media SET backdrop=? WHERE id=?");
        $stmt->execute([$backdropPath, $id]);
    }

    // --- Automatic cover/backdrop update ---
    $autoCover = $mediaCacheFolder . '/cover.jpg';
    $autoBackdrop = $mediaCacheFolder . '/backdrop.jpg';

    if(file_exists($autoCover)){
        $coverDbPath = "media_cache/{$cleanTitle}/cover.jpg";
        $stmt = $pdo->prepare("UPDATE media SET cover=? WHERE id=?");
        $stmt->execute([$coverDbPath, $id]);
    }

    if(file_exists($autoBackdrop)){
        $backdropDbPath = "media_cache/{$cleanTitle}/backdrop.jpg";
        $stmt = $pdo->prepare("UPDATE media SET backdrop=? WHERE id=?");
        $stmt->execute([$backdropDbPath, $id]);
    }

    // Update title/type/season/episode in DB
    $stmt = $pdo->prepare("UPDATE media SET title=?, type=?, season=?, episode=? WHERE id=?");
    $stmt->execute([$title, $type, $season, $episode, $id]);

    header("Location: my_media.php?success=1");
    exit;
}

// --- Fetch all media ---
$mediaList = $pdo->query("SELECT * FROM media ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Media</title>
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
.table td,.table th{white-space:nowrap; vertical-align:middle;}
.media-cover{height:120px;margin-right:10px; cursor:pointer;}
.media-backdrop{height:80px;margin-right:10px; cursor:pointer;}
.form-control, .form-select {font-size:0.875rem;}
.btn-sm {font-size:0.8rem; padding:0.25rem 0.5rem;}
footer{background:#000;text-align:center;padding:1rem;margin-top:auto;}
</style>
</head>
<body>
<!-- Sidebar -->
<div id="sidebar">
  <div class="logo">
     <img src="<?=h(getLogoPath())?>" alt="MiniMedia"></div>
  <div class="profile">
    <div class="ms-auto dropdown">
    <a class="nav-link dropdown-toggle d-flex align-items-center text-light" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
      <div class="rounded-circle overflow-hidden me-2" style="width:40px; height:40px;">
        <img src="<?=h($profileImage)?>" class="w-100 h-100" style="object-fit:cover;">
      </div>
      <?=h($usr['username'])?>
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
<div class="container">
<h3>Manage Media</h3>
<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">Media updated successfully</div>
<?php endif; ?>

<table class="table table-dark table-striped">
<thead>
<tr>
<th>Title</th>
<th>Cover</th>
<th>Backdrop</th>
<th>Type</th>
<th>Season</th>
<th>Episode</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($mediaList as $m):
    $coverPath = getDynamicCover($m['title'], $m['type']);
	$backdropPath = getDynamicBackdrop($m['title'], $m['type']);
?>
<tr>
<td><?=h($m['title'])?></td>

<td>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?=h($m['id'])?>">
<img src="<?=h($coverPath)?>" class="media-cover" onclick="this.nextElementSibling.click()">
<input type="file" name="manual_cover" style="display:none" onchange="this.form.submit()">
</form>
</td>

<td>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?=h($m['id'])?>">
<img src="<?=h($backdropPath)?>" class="media-backdrop" onclick="this.nextElementSibling.click()">
<input type="file" name="manual_backdrop" style="display:none" onchange="this.form.submit()">
</form>
</td>

<td>
<form method="post">
<input type="hidden" name="id" value="<?=h($m['id'])?>">
<select name="type" class="form-select form-select-sm">
<option value="movie" <?=$m['type']==='movie'?'selected':''?>>Movie</option>
<option value="tv" <?=$m['type']==='tv'?'selected':''?>>TV</option>
<option value="music" <?=$m['type']==='music'?'selected':''?>>Music</option>
</select>
</td>

<td><input type="text" class="form-control form-control-sm" name="season" value="<?=h($m['season'])?>"></td>
<td><input type="text" class="form-control form-control-sm" name="episode" value="<?=h($m['episode'])?>"></td>
<td>
<input type="text" class="form-control form-control-sm mb-1" name="title" value="<?=h($m['title'])?>">
<button type="submit" class="btn btn-success btn-sm">Rescan / Update</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="index.php">MrMat</a></p>
</footer>
</body>
</html>