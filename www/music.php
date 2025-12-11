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

// --- Filters ---
$search = trim($_GET['q'] ?? '');
$artistFilter = $_GET['artist'] ?? '';
$albumFilter = $_GET['album'] ?? '';

// --- Fetch tracks ---
// --- Fetch tracks (music only) ---
$params = [];
$sql = "SELECT * FROM media WHERE type = 'music'"; // <-- only music

if ($search) {
    $sql .= " AND (title LIKE ? OR artist LIKE ? OR album LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
if ($artistFilter) {
    $sql .= " AND artist = ?";
    $params[] = $artistFilter;
}
if ($albumFilter) {
    $sql .= " AND album = ?";
    $params[] = $albumFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Artists & Albums for dropdown ---
$artists = $pdo->query("SELECT DISTINCT artist FROM media ORDER BY artist")->fetchAll(PDO::FETCH_COLUMN);
$albums = $pdo->query("SELECT DISTINCT album FROM media ORDER BY album")->fetchAll(PDO::FETCH_COLUMN);

// Determine media type
$media_type = $_GET['type'] ?? 'music';
$stmt = $pdo->prepare("SELECT * FROM media WHERE type = ? ORDER BY title");
$stmt->execute([$media_type]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Music</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="assets/css/toast.css">
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/enhancements.css">
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
#player {position:fixed; bottom:0; left:0; right:0; background:#000; padding:10px; display:flex; align-items:center; gap:10px; z-index:2000; border-top: 1px solid #333;}
#player audio {flex:1;}
.form-inline {display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;}
.form-inline input, .form-inline select {padding:6px 10px; border-radius:5px; border:1px solid #ccc;}
.form-inline button {flex:none;}
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
</style>
</head>
<body class="bg-dark">

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
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="tv.php?type=tv">TV</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2 active" href="music.php?type=music">Music</a></li>
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
  <h3 class="mb-4">🎵 Music Library</h3>

  <!-- Search & Filters -->
  <form method="get" class="form-inline">
    <input type="text" name="q" placeholder="Search by title..." value="<?=htmlspecialchars($search)?>">
    <select name="artist">
      <option value="">All Artists</option>
      <?php foreach($artists as $a): ?>
        <option value="<?=htmlspecialchars($a)?>" <?= $artistFilter === $a ? 'selected' : '' ?>><?=htmlspecialchars($a)?></option>
      <?php endforeach; ?>
    </select>
    <select name="album">
      <option value="">All Albums</option>
      <?php foreach($albums as $al): ?>
        <option value="<?=htmlspecialchars($al)?>" <?= $albumFilter === $al ? 'selected' : '' ?>><?=htmlspecialchars($al)?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>

  <div class="movie-row">
    <?php if($tracks): foreach($tracks as $t): ?>
      <a class="movie-card" href="#" onclick="playTrack('<?=htmlspecialchars($t['short_url'])?>','<?=htmlspecialchars(addslashes($t['title']))?>'); return false;">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?=htmlspecialchars($t['cover'])?>" alt="cover" class="lazy-load">
        <button class="watchlist-btn" data-media-id="<?= $t['id'] ?>" data-media-type="music">
                <i class="fas fa-heart"></i>
            </button>
			<div class="overlay">
            <span class="title"><?=htmlspecialchars($t['title'])?></span>
        </div>
      </a>
    <?php endforeach; else: ?>
      <p class="text-center text-warning">No tracks found.</p>
    <?php endif; ?>
  </div>

  <div id="player" style="display:none;">
    <strong id="nowPlaying" class="me-3">Now Playing: </strong>
    <audio id="audioPlayer" controls></audio>
  </div>
</div>

<script>
function playTrack(shortUrl, title) {
  const player = document.getElementById('player');
  const audio = document.getElementById('audioPlayer');
  const nowPlaying = document.getElementById('nowPlaying');

  player.style.display = 'flex';
  nowPlaying.innerText = "Now Playing: " + title;

  audio.src = "stream_music.php?s=" + encodeURIComponent(shortUrl);
  audio.volume = 0.2; // Set initial volume to 10%
  audio.play();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/toast.js"></script>
<script src="assets/js/loading.js"></script>
<script src="assets/js/lazy-load.js"></script>
<script src="assets/js/search.js"></script>
<script src="assets/js/watchlist.js"></script>
<script src="assets/js/rating.js"></script>

<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>