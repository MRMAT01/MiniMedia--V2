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

$short = $_GET['short'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM media WHERE short_url = ? AND type = 'movie'");
$stmt->execute([$short]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    die("Movie not found.");
}

$overview = 'No overview available.';

// Try to read from the movie's cache JSON
$jsonFile = __DIR__ . '/media_cache/' . $movie['title'] . '/' . $movie['title'] . '.json';
if (file_exists($jsonFile)) {
    $jsonData = json_decode(file_get_contents($jsonFile), true);
    if (!empty($jsonData['overview'])) {
        $overview = $jsonData['overview'];
		$tagline = $jsonData['tagline'];
		$genres = $jsonData['genres'];
    }
}

// Get genres for this movie
$genreStmt = $pdo->prepare("
    SELECT g.name 
    FROM genres g
    INNER JOIN media_genres mg ON g.id = mg.genre_id
    WHERE mg.media_id = ?
");
$genreStmt->execute([$movie['id']]);
$genres = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
$genreList = $genres ? implode(', ', $genres) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($movie['title']) ?> - MiniMedia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {
  height: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1 0 auto; /* makes main content grow and push footer down */
}

body {
    background: #111 url("image.php?s=<?= urlencode($movie['short_url']) ?>&type=backdrop") no-repeat center center fixed;
    background-size: cover;      /* fills the viewport */
    background-repeat: no-repeat; /* prevents tiling */
    background-position: center center;
    color: #fff;
}

/* Backdrop container */
.backdrop-container {
    position: relative;
    width: 100%;
    min-height: 100vh;

    background-size: cover;      /* fill screen without repeating */
}

/* Optional: dark overlay for readability */
.backdrop-overlay {
    backdrop-filter: blur(6px) brightness(0.5);
    padding: 20px;
    border-radius: 10px;
    color: #fff;
}

/* Cover image */
.movie-cover {
    width: 100%;        /* scale with column */
    max-width: 300px;   /* optional max size */
    height: auto;       /* maintain aspect ratio */
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.6);
    display: block;
    margin-bottom: 1rem;
}

/* Ensure no repeating for backdrop */
body, .backdrop-container {
    margin: 0;
    padding: 0;
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

.navbar-custom { position: sticky; top: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; }
.profile-pic { width: 50px; height: 50px; border-radius: 50%; overflow: hidden; }
.profile-pic img { width:100%; height:100%; object-fit: cover; }
.show-header { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 2rem; }
.show-header img { width: 200px; border-radius: 10px; }

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
  flex-shrink: 0;
}
</style>
</head>
<body class="text-white">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
      <div class="container-fluid">
    <a class="navbar-brand" href="index.php">
        <img src="<?= getLogoPath() ?>" height="60" alt="MiniMedia">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="btn btn-sm btn-success me-2" href="index.php">Home</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2 active" href="home.php?type=movie">Movies</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="tv.php?type=tv">TV</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="music.php?type=music">Music</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="featured.php?type=featured">Featured</a></li>
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
<!-- Movie Info -->
<main>
<div class="container py-5 backdrop-overlay">
  <div class="row">
    <div class="col-md-4">
      <img src="image.php?s=<?= urlencode($movie['short_url']) ?>&type=cover" 
           class="movie-cover" 
           alt="<?= htmlspecialchars($movie['title']) ?>">
    </div>
    <div class="col-md-8">
      <h2><?= htmlspecialchars($movie['title']) ?></h2>
      <p><strong>Year:</strong> <?= date('Y', strtotime($movie['created_at'])) ?></p>
      <p><strong>Genre:</strong> <?= htmlspecialchars($genreList) ?></p>
	  <p><strong>Tagline:</strong> <?= htmlspecialchars($tagline) ?></p>
      <p><strong>Overview:</strong> <?= htmlspecialchars($overview) ?></p>
      <!-- ✅ Trigger button (outside modal) -->
      <button class="btn btn-danger btn-lg" onclick="playMovie('<?= $movie['short_url'] ?>', '<?= addslashes($movie['title']) ?>')">
        ▶ Play
      </button>
    </div>
  </div>
</div>
</main>

<!-- ✅ Modal -->
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

<script>
function playMovie(shortUrl, title) {
  const video = document.getElementById('videoPlayer');
  const source = document.getElementById('videoSource');
  const modalTitle = document.getElementById('playerModalTitle');

  // Set video source dynamically
  source.src = "stream.php?s=" + encodeURIComponent(shortUrl);
  modalTitle.innerText = title;
  video.load(); // reload source

  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('playerModal'));
  modal.show();

  // Play automatically once ready
  video.addEventListener('canplay', () => {
    video.play();
  }, { once: true });
}

// Stop video when modal closes
document.getElementById('playerModal').addEventListener('hidden.bs.modal', () => {
  const video = document.getElementById('videoPlayer');
  video.pause();
  video.currentTime = 0;
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>