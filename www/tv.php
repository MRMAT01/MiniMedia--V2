<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Profile image
$profileImage = "profile/guest.png";
if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])) {
        $profileImage = $user['profile_image'];
    }
}

$defaultCover = 'media_cache/noimage.png';

// Helper: normalize title/folder name
function normalizeTitleForFolder($titleOrFilename) {
    $baseName = preg_replace('/\.(mp4|mkv|avi|mov|flv|mp3)$/i','',$titleOrFilename);
    $clean = preg_replace(
        '/(\[.*?\]|\(.*?\)|\b(5|5.1|4k|2160p|1080p|720p|480p|HDR|HDR10|DV|DDP5|WEB-?DL|WEBRip|WEB|BluRay|BRRip|BDRip|DVDRip|HDTV|HDTS|CAM|TS|TC|x264|x265|h264|hevc|10bit|[0-9]{1,4}MB|[0-9]{1,2}GB|NF|AMZN|HULU|MAX|UHD|HDRip|PROPER|REPACK|LIMITED|EXTENDED|UNRATED|YTS|RARBG|EVO|P2P|AAC|AC5|Rapta|BONE|RMTeam|WORKPRINT|COLLECTiVE|sylix|MeGusta|EZTVx|to|FENiX|ETHEL|nhtfs|ELiTE|ATVP|NTb|successfulcrab|CRAV|SNAKE|SYNCOPY|JFF|AFG|FGT|FLEET|Galaxy(RG|TV)?|RIP|Ganool|YIFY|EVO|P2P|U2|NeoNoir|TGx|MX|AM|RARBG|ETRG|Eng|ESub|DD(5|1)?|LAMA)\b)/i',
        '',
        $baseName
    );
    $clean = preg_replace('/S\d{2}E\d{2}/i','', $clean);
    $clean = preg_replace('/\b\d+x\d+\b/i', '', $clean);
    $clean = preg_replace('/[\[\]\(\)\._-]+/',' ', $clean);
    $clean = trim(preg_replace('/\s+/',' ', $clean));
    $clean = preg_replace('/[^A-Za-z0-9 _-]/','', $clean);
    return trim($clean) ?: 'unknown';
}

// Optional genre filter
$genre_id = $_GET['genre'] ?? null;

// Fetch all TV episodes
$sql = "SELECT m.*, GROUP_CONCAT(g.name, ',') AS genres
        FROM media m
        LEFT JOIN media_genres mg ON m.id = mg.media_id
        LEFT JOIN genres g ON g.id = mg.genre_id
        WHERE m.type='tv'";
$params = [];
if ($genre_id) {
    $sql .= " AND m.id IN (SELECT media_id FROM media_genres WHERE genre_id = :genre_id)";
    $params['genre_id'] = $genre_id;
}
$sql .= " GROUP BY m.id ORDER BY m.path";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build TV shows array by normalized folder name (one card per show)
$tv_shows = [];
foreach ($rows as $ep) {
    $filename = pathinfo($ep['path'], PATHINFO_FILENAME);
    $folder = normalizeTitleForFolder($filename ?: $ep['title']);

    if (!isset($tv_shows[$folder])) {
        $tv_shows[$folder] = [
            'id' => $ep['id'], // Add the ID of the first episode as the show's ID
            'title' => $folder,
            'cover' => $ep['cover'] ?? null,
            'genres' => $ep['genres'] ?? ''
        ];
    }
}

// Get all genres
$allGenres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - TV Shows</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="assets/css/toast.css">
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/enhancements.css?v=<?= time() ?>">
<style>
html, body {height:100%; margin:0; background:url('images/bg.jpg') fixed no-repeat; background-size:cover; color:#fff;}
body {display:flex; flex-direction:column;}
.container {flex:1;}
.navbar-custom {position:sticky; top:0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index:1000; border-bottom:1px solid rgba(255,255,255,0.1);}
.profile-pic {width:50px; height:50px; border-radius:50%; overflow:hidden;}
.profile-pic img {width:100%; height:100%; object-fit:cover;}
.movie-row { display:flex; flex-wrap:wrap; gap:15px; justify-content:flex-start; }
.movie-card { width:160px; text-align:center; border-radius:10px; overflow:hidden; background:#222; transition: transform 0.2s ease, box-shadow 0.2s ease; text-decoration:none; color:#ccc; }
.movie-card:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
.movie-card img { width:100%; height:220px; object-fit:cover; border-bottom:1px solid #444; }
.movie-card .overlay { padding:8px 5px; }
.movie-card .title { display:block; margin-top:5px; font-size:0.9rem; color:#fff; font-weight:500; }
.movie-card .genre { display:block; font-size:0.8rem; color:#aaa; margin-top:2px; }
.page-content { padding-top: 90px; padding-bottom: 60px; min-height: calc(100vh - 160px); }
footer { background: #000; color: #ccc; text-align: center; padding: 1rem 0; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><img src="<?= getLogoPath() ?>" height="60" alt="MiniMedia"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="btn btn-sm btn-success me-2" href="index.php">Home</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="home.php?type=movie">Movies</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2 active" href="tv.php?type=tv">TV</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="music.php?type=music">Music</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="featured.php?type=featured">Featured</a></li>
      </ul>
      
      <div class="global-search-bar mx-3" style="max-width: 400px;">
        <input type="text" id="globalSearch" class="form-control" placeholder="Search movies, TV, music...">
        <i class="fas fa-search search-icon"></i>
        <div id="globalSearchResults" class="search-results-container"></div>
      </div>

      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item"><a class="btn btn-sm btn-outline-light me-2" href="watchlist.php"><i class="fas fa-heart"></i> Watchlist</a></li>
      </ul>

      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 profile-menu">
        <li class="nav-item dropdown">
          <a class="nav-link" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
            <div class="profile-pic"><img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile"></div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-fw"></i> Profile</a></li>
                <li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="register.php"><i class="fas fa-sign-in-alt fa-fw"></i> Register</a></li>
				<li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt fa-fw"></i> Login</a></li>
				<li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Log Out</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4 text-white">
  <h3 class="mb-4">📺 TV Library</h3>
  <div class="movie-row">
    <?php foreach($tv_shows as $folder => $show): ?>
    <a class="movie-card" href="tv_show.php?show=<?= urlencode($folder) ?>">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?= htmlspecialchars($show['cover']) ?>" alt="<?= htmlspecialchars($show['title']) ?>" class="lazy-load">
		<button class="watchlist-btn" data-media-id="<?= $show['id'] ?>" data-media-type="tv"></button>
        <div class="overlay">
            <span class="title"><?= htmlspecialchars($show['title']) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/toast.js"></script>
<script src="assets/js/loading.js"></script>
<script src="assets/js/lazy-load.js"></script>
<script src="assets/js/search.js"></script>
<script src="assets/js/watchlist.js"></script>
<script src="assets/js/rating.js"></script>

<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>