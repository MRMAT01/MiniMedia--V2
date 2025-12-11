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

// Filters
$search = $_GET['search'] ?? '';
$genre_id = $_GET['genre'] ?? null;

// Featured category
$featuredCategoryId = 8;

// Build query
$sql = "
    SELECT m.id, m.title, m.short_url, m.path, m.created_at,
           GROUP_CONCAT(g.name) AS genres
    FROM media m
    INNER JOIN media_categories mc ON m.id = mc.media_id
    LEFT JOIN media_genres mg ON m.id = mg.media_id
    LEFT JOIN genres g ON mg.genre_id = g.id
    WHERE mc.category_id = :featured_id
";

$params = ['featured_id' => $featuredCategoryId];

// Apply search filter
if (!empty($search)) {
    $sql .= " AND m.title LIKE :search";
    $params['search'] = "%$search%";
}

// Apply genre filter
if (!empty($genre_id)) {
    $sql .= " AND m.id IN (SELECT media_id FROM media_genres WHERE genre_id = :genre_id)";
    $params['genre_id'] = $genre_id;
}

// Group by media.id to allow GROUP_CONCAT
$sql .= " GROUP BY m.id ORDER BY m.title";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$featured = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Featured - MiniMedia</title>
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
/* Force white text in global search - override cached CSS */
#globalSearch, .global-search-bar input { color: #fff !important; }
#globalSearch::placeholder, .global-search-bar input::placeholder { color: #999 !important; }
#globalSearch:-webkit-autofill, .global-search-bar input:-webkit-autofill { -webkit-text-fill-color: #fff !important; }
</style>
</head>
<body>

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
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="home.php?type=movie">Movies</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="tv.php?type=tv">TV</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="music.php?type=music">Music</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-warning active me-2" href="featured.php">Featured</a></li>
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
          <ul class="dropdown-menu dropdown-menu-end">
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

<!-- Main Content -->
<main>
<div class="container py-4 text-white">
  <!-- Featured Grid -->
  <div class="container text-white">
    <h3 class="mb-4 text-left">🌟 Featured Media</h3>
    <?php if (count($featured) > 0): ?>
      <div class="movie-row">
        <?php foreach ($featured as $item): ?>
          <a href="featured_media.php?short=<?= urlencode($item['short_url']) ?>" class="movie-card">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="image.php?s=<?= urlencode($item['short_url']) ?>&type=cover" alt="<?= htmlspecialchars($item['title']) ?>" class="lazy-load">
			<button class="watchlist-button" data-media-id="<?= $item['id'] ?>" data-media-type="featured"><i class="fas fa-heart"></i></button>
            <div class="overlay">
              <span class="title"><?= htmlspecialchars($item['title']) ?></span>
              
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-center text mt-4">No featured media found.</p>
    <?php endif; ?>
  </div>
</div>
</main>
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