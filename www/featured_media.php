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

// Get featured item by short URL
$short = $_GET['short'] ?? '';

$stmt = $pdo->prepare("
    SELECT m.*
    FROM media m
    INNER JOIN media_categories mc ON m.id = mc.media_id
    WHERE mc.category_id = 8 AND m.short_url = ?
");
$stmt->execute([$short]);
$featured = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$featured) {
    die("Featured media not found.");
}

$overview = 'No overview available.';

// Try to read from the movie's cache JSON
$jsonFile = __DIR__ . '/media_cache/' . $featured['title'] . '/' . $featured['title'] . '.json';
if (file_exists($jsonFile)) {
    $jsonData = json_decode(file_get_contents($jsonFile), true);
    if (!empty($jsonData['overview'])) {
        $overview = $jsonData['overview'];
    }
}

// Get genres for this movie
$genreStmt = $pdo->prepare("
    SELECT g.name 
    FROM genres g
    INNER JOIN media_genres mg ON g.id = mg.genre_id
    WHERE mg.media_id = ?
");
$genreStmt->execute([$featured['id']]);
$genres = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
$genreList = $genres ? implode(', ', $genres) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($featured['title']) ?> - MiniMedia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="assets/css/toast.css">
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/enhancements.css">
<style>
html, body {
  height: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1 0 auto;
}

body {
    background: #111 url("image.php?s=<?= urlencode($featured['short_url']) ?>&type=backdrop") no-repeat center center fixed;
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center center;
    color: #fff;
}

.backdrop-overlay {
    backdrop-filter: blur(6px) brightness(0.5);
    padding: 20px;
    border-radius: 10px;
    color: #fff;
}

.movie-cover {
    width: 100%;
    max-width: 300px;
    height: auto;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.6);
    display: block;
    margin-bottom: 1rem;
}

.modal-dialog {
    max-width: 80%;
}

.modal-body {
    padding: 0;
    background: #000;
}

body.modal-open {
  padding-right: 0 !important;
  overflow-y: hidden;
}

video {
    width: 100%;
    height: auto;
}

.navbar-custom { position: sticky; top: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1); }
.profile-pic { width: 50px; height: 50px; border-radius: 50%; overflow: hidden; }
.profile-pic img { width:100%; height:100%; object-fit: cover; }

footer {
  background: #000;
  color: #ccc;
  text-align: center;
  padding: 1rem 0;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 20px;
  flex-shrink: 0;
}
</style>
</head>
<body class="text-white">

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
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="tv.php?type=tv">TV</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="music.php?type=music">Music</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2 active" href="featured.php?type=featured">Featured</a></li>
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
          <a class="nav-link" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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

<main>
<div class="container py-5 backdrop-overlay">
  <div class="row">
    <div class="col-md-4">
      <img src="image.php?s=<?= urlencode($featured['short_url']) ?>&type=cover" 
           class="movie-cover" 
           alt="<?= htmlspecialchars($featured['title']) ?>">
    </div>
    <div class="col-md-8">
      <h2><?= htmlspecialchars($featured['title']) ?></h2>
      
	        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="rating-container" data-media-id="<?= $featured['id'] ?>" data-media-type="featured"></div>
          <span class="badge bg-secondary"><?= date('Y', strtotime($featured['created_at'])) ?></span>
      </div>
      <p><strong>Genre:</strong> <?= htmlspecialchars($genreList) ?></p>
      <div class="mb-3">
        <button class="btn btn-danger btn-lg me-2" onclick="playMovie('<?= $featured['short_url'] ?>', '<?= addslashes($featured['title']) ?>', <?= $featured['id'] ?>)">
            <i class="fas fa-play"></i> Play
        </button>
        <button class="btn btn-outline-light btn-lg watchlist-button" data-media-id="<?= $featured['id'] ?>" data-media-type="featured">
            <i class="fas fa-heart"></i> Watchlist
        </button>
        <a href="download.php?id=<?= $featured['id'] ?>&type=featured" class="btn btn-outline-secondary btn-lg">
            <i class="fas fa-download"></i> Download
        </a>
      </div>

      <p><strong>Overview:</strong> <?= htmlspecialchars($overview) ?></p>
    </div>
  </div>
</div>
</main>

<!-- Modal -->
<div class="modal fade" id="playerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-black">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="playerModalTitle"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <video id="videoPlayer" controls autoplay style="width:100%;height:auto;">
          <source id="videoSource" src="" type="video/mp4">
          Your browser does not support HTML5 video.
        </video>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/toast.js"></script>
<script src="assets/js/loading.js"></script>
<script src="assets/js/lazy-load.js"></script>
<script src="assets/js/search.js"></script>
<script src="assets/js/watchlist.js"></script>
<script src="assets/js/rating.js"></script>
<script src="assets/js/progress.js"></script>
<script>
function playMovie(shortUrl, title, mediaId) {
  const video = document.getElementById('videoPlayer');
  const source = document.getElementById('videoSource');
  const modalTitle = document.getElementById('playerModalTitle');

  source.src = "stream.php?s=" + encodeURIComponent(shortUrl);
  modalTitle.innerText = title;
  video.load();

  const modal = new bootstrap.Modal(document.getElementById('playerModal'));
  modal.show();

  video.addEventListener('canplay', () => {
    video.play();
    if (mediaId) {
        ProgressManager.init(video, mediaId);
    }
  }, { once: true });
}

document.getElementById('playerModal').addEventListener('hidden.bs.modal', () => {
  const video = document.getElementById('videoPlayer');
  video.pause();
  video.currentTime = 0;
});
</script>

<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>