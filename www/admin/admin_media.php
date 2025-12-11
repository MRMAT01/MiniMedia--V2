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

// --- Normalize title ---
function normalizeTitleForFolder($titleOrFilename) {
    $baseName = preg_replace('/\.(mp4|mkv|avi|mov|flv|mp3)$/i', '', $titleOrFilename);
    $clean = preg_replace('/(\[.*?\]|\(.*?\)|\b(5|5.1|4k|2160p|1080p|720p|480p|HDR|HDR10|DV|DDP5|WEB-?DL|WEBRip|WEB|BluRay|BRRip|BDRip|DVDRip|HDTV|HDTS|CAM|TS|TC|x264|x265|h264|hevc|10bit|[0-9]{1,4}MB|[0-9]{1,2}GB|NF|AMZN|HULU|MAX|UHD|HDRip|PROPER|REPACK|LIMITED|EXTENDED|UNRATED|YTS|RARBG|RGB|EVO|P2P|AAC|AC5|Rapta|BONE|RMTeam|WORKPRINT|COLLECTiVE|sylix|MeGusta|EZTVx|to|FENiX|ETHEL|nhtfs|ELiTE|ATVP|NTb|successfulcrab|CRAV|SNAKE|SYNCOPY|JFF|AFG|FGT|FLEET|Galaxy(RG|TV)?|RIP|Ganool|YIFY|EVO|P2P|U2|NeoNoir|TGx|MX|AM|RARBG|ETRG|Eng|ESub|DD(5|1)?|LAMA|Teema|Sagrona|XviD)\b)/i', '', $baseName);
    $clean = preg_replace('/S\d{1,2}E\d{1,2}/i', '', $clean);
    $clean = preg_replace('/\b\d+x\d+\b/i', '', $clean);
    $clean = trim(preg_replace('/[\[\]\(\)\._-]+/', ' ', $clean));
    $clean = preg_replace('/[^A-Za-z0-9 _-]/', '', $clean);
    return trim($clean) ?: 'unknown';
}

// --- Recursive folder delete ---
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isDir()) {
            rrmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($dir);
}

// --- Get media image ---
function getMediaImagePath($media, $type='cover') {
    $projectRoot = __DIR__ . '/../';

    $file = $media[$type] ?? '';
    if ($file && file_exists($projectRoot . ltrim($file, '/\\'))) {
        return '../' . ltrim($file, '/\\') . '?v=' . time();
    }

    if ($media['type'] === 'tv') {
        $baseFolder = $projectRoot . 'media_cache/' . normalizeTitleForFolder($media['title']);
        if (is_dir($baseFolder)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseFolder, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (strtolower($file->getFilename()) === ($type === 'cover' ? 'cover.jpg' : 'backdrop.jpg')) {
                    return '../' . str_replace('\\','/',$file->getPathname()) . '?v=' . time();
                }
            }
        }
        return $type === 'cover' ? '../images/noimage.png' : '../images/no_backdrop.png';
    }

    if ($media['type'] === 'music') return '../images/noimage.png';
    return '../images/noimage.png';
}

// ------------------------
// DELETE MEDIA
// ------------------------

// Make sure rrmdir() is declared only once
if (!function_exists('rrmdir')) {
    function rrmdir($dir) {
        if (!is_dir($dir)) return;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $pathname = $fileinfo->getPathname();
            if ($fileinfo->isDir()) {
                rrmdir($pathname);
            } else {
                @unlink($pathname);
            }
        }
        @rmdir($dir);
    }
}

// Check if a delete request is made
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($media) {
        $projectRoot = realpath(__DIR__ . '/../');
        $candidates = [];

        // Delete media files
        foreach (['path', 'cover', 'backdrop'] as $col) {
            if (!empty($media[$col])) {
                $full = __DIR__ . '/../' . ltrim($media[$col], '/\\');
                if (file_exists($full)) $candidates[] = $full;
            }
        }

        // Delete media_cache folder
        $cacheFolder = __DIR__ . '/../media_cache/' . normalizeTitleForFolder($media['title']);
        if (is_dir($cacheFolder)) $candidates[] = $cacheFolder;

        // Delete TV folder if type is TV
        if ($media['type'] === 'tv') {
            $tvFolder = __DIR__ . '/../tv/' . normalizeTitleForFolder($media['title']);
            if (is_dir($tvFolder)) $candidates[] = $tvFolder;
        }

        // Delete everything
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false || strpos($real, $projectRoot) !== 0) continue;
            if (is_dir($real)) rrmdir($real);
            else @unlink($real);
        }

        // Delete DB entries
        $pdo->prepare("DELETE FROM media_categories WHERE media_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM media_genres WHERE media_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM media WHERE id = ?")->execute([$id]);
    }

    header("Location: admin_media.php?deleted=1");
    exit;
}

// ------------------------
// MANUAL COVER/BACKDROP UPLOAD
// ------------------------
foreach (['manual_cover'=>'cover','manual_backdrop'=>'backdrop'] as $fileKey => $col) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['tmp_name']) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT id, title, type FROM media WHERE id=?");
        $stmt->execute([$id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($media) {
            $cleanTitle = normalizeTitleForFolder($media['title']);
            $folder = __DIR__ . "/../media_cache/{$cleanTitle}";
            @mkdir($folder, 0777, true);
            $targetFile = $col === 'cover' ? 'cover.jpg' : 'backdrop.jpg';
            $targetPath = "{$folder}/{$targetFile}";
            move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath);
            $pdo->prepare("UPDATE media SET $col=? WHERE id=?")->execute(["media_cache/{$cleanTitle}/{$targetFile}", $id]);
            header("Location: admin_media.php?success=1&v=" . time());
            exit;
        }
    }
}

// ------------------------
// UPDATE MEDIA INFO
// ------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_media'])) {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $genres = $_POST['genres'] ?? [];
    $categories = $_POST['categories'] ?? [];

    $pdo->prepare("UPDATE media SET title=? WHERE id=?")->execute([$title, $id]);

    $pdo->prepare("DELETE FROM media_categories WHERE media_id=?")->execute([$id]);
    foreach ($categories as $cat) $pdo->prepare("INSERT INTO media_categories (media_id, category_id) VALUES (?, ?)")->execute([$id, (int)$cat]);

    $pdo->prepare("DELETE FROM media_genres WHERE media_id=?")->execute([$id]);
    foreach ($genres as $g) $pdo->prepare("INSERT INTO media_genres (media_id, genre_id) VALUES (?, ?)")->execute([$id, $g]);

    header("Location: admin_media.php?success=1");
    exit;
}

// ------------------------
// FETCH MEDIA LIST
// ------------------------
$mediaList = $pdo->query("
    SELECT m.id, m.title, m.cover, m.backdrop, m.type,
           (SELECT GROUP_CONCAT(name, ', ') 
            FROM (SELECT g2.name 
                  FROM media_genres mg2 
                  JOIN genres g2 ON g2.id = mg2.genre_id 
                  WHERE mg2.media_id = m.id 
                  ORDER BY g2.name)
           ) AS genre_names
    FROM media m
    ORDER BY m.type, m.title
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allGenres  = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Admin Media</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
body {margin:0; font-family:sans-serif; background:#121212; color:#fff;}
.custom-img{height:280px;object-fit:cover;width:100%;border-radius:6px;cursor:pointer;}
.genre-select{min-width:200px;}
.genre-label{font-size:0.85rem;color:#ddd;margin-bottom:0.25rem;}
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
<h1 class="mb-4">Manage Media</h1>
<?php if(isset($_GET['success'])): ?><div class="alert alert-success">Media updated successfully</div><?php endif; ?>

<?php foreach ($mediaList as $m):
    $assignedCategories = $pdo->query("SELECT category_id FROM media_categories WHERE media_id=".$m['id'])->fetchAll(PDO::FETCH_COLUMN);
    $assignedGenres = $pdo->query("SELECT genre_id FROM media_genres WHERE media_id=".$m['id'])->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card bg-secondary mb-4">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $m['id'] ?>">

      <?php if (!empty($m['genre_names'])): ?>
        <div class="genre-label mb-1">Genres: <?= htmlspecialchars($m['genre_names']) ?></div>
      <?php endif; ?>

      <div class="row g-3 align-items-center">
        <div class="col-md-3">
            <img src="<?= htmlspecialchars(getMediaImagePath($m,'cover')) ?>" class="custom-img mb-2" onclick="this.nextElementSibling.click()">
            <input type="file" name="manual_cover" style="display:none" onchange="this.form.submit()">
        </div>
        <div class="col-md-3">
            <img src="<?= htmlspecialchars(getMediaImagePath($m,'backdrop')) ?>" class="custom-img mb-2" onclick="this.nextElementSibling.click()">
            <input type="file" name="manual_backdrop" style="display:none" onchange="this.form.submit()">
        </div>
        <div class="col-md-6">
          <input type="text" name="title" class="form-control mb-2" value="<?= htmlspecialchars($m['title']) ?>" required>

          <label>Genres</label>
          <select name="genres[]" class="form-select genre-select mb-2" multiple>
            <?php foreach ($allGenres as $g): ?>
              <option value="<?= $g['id'] ?>" <?= in_array($g['id'], $assignedGenres) ? 'selected' : '' ?>>
                <?= htmlspecialchars($g['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label>Categories</label>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach ($categories as $cat): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $assignedCategories) ? 'checked' : '' ?>>
                <label class="form-check-label"><?= htmlspecialchars($cat['name']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" name="update_media" class="btn btn-success" onclick="return confirm('Confirm Save?')">Save</button>
            <a href="?delete=<?= $m['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this media?')">Delete</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="index.php">MrMat</a></p>
</footer>
</body>
</html>