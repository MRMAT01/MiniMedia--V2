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

// Helper: normalize title/folder name (same as tv.php)
function normalizeTitleForFolder($titleOrFilename) {
    $baseName = preg_replace('/\.(mp4|mkv|avi|mov|flv|mp3)$/i','',$titleOrFilename);
    $clean = preg_replace(
        '/(\[.*?\]|\(.*?\)|\b(720p|1080p|480p|2160p|WEBRip|WEB|BluRay|BRRip|HDRip|HDTV|AMZN|NF|MAX|x264|x265|HEVC|10bit|FENiX|XviD|EZTVx\.to|AFG|YTS|RARBG|PROPER|REPACK|AAC|DDP5|WEB-DL|DVDRip|NTb|MeGusta|GalaxyTV|ELiTE)\b)/i',
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

// Validate input
$show = $_GET['show'] ?? '';
if (!$show) die("Invalid show");

// Fetch all episodes for this show folder
$stmt = $pdo->prepare("
    SELECT m.*, GROUP_CONCAT(g.name, ', ') AS genres
    FROM media m
    LEFT JOIN media_genres mg ON m.id = mg.media_id
    LEFT JOIN genres g ON g.id = mg.genre_id
    WHERE m.type = 'tv' AND (
        REPLACE(path, '.', '') LIKE :show
        OR REPLACE(title, ' ', '') = REPLACE(:show, ' ', '')
    )
    GROUP BY m.id
    ORDER BY season, episode
");
$stmt->execute(['show' => "%$show%"]);
$episodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$episodes) {
    die("Show not found.");
}

// Show info from first episode
$showInfo = $episodes[0];
$cover = $showInfo['cover'] ?? 'images/nocover.png';
$backdrop = $showInfo['backdrop'] ?? 'images/no_backdrop.png';

// Collect all genres from episodes
$allGenres = [];
foreach ($episodes as $ep) {
    if (!empty($ep['genres'])) {
        foreach (explode(',', $ep['genres']) as $g) {
            $allGenres[trim($g)] = true;
        }
    }
}
$genre = implode(', ', array_keys($allGenres));

// Determine folder name from the first episode
$folder = normalizeTitleForFolder(pathinfo($showInfo['path'], PATHINFO_FILENAME));

// Default overview
$overview = 'No overview available.';

// Look for a folder-level JSON in media_cache by folder name
$firstEpisodeFile = __DIR__ . '/media_cache/' . $folder . '/' . $episodes[0]['title'] . '.json';
if (file_exists($firstEpisodeFile)) {
    $jsonData = json_decode(file_get_contents($firstEpisodeFile), true);
    if (!empty($jsonData['overview'])) {
        $overview = $jsonData['overview'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - <?= htmlspecialchars($folder) ?> - Episodes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {height:100%; margin:0; background:#111 url('<?= htmlspecialchars($backdrop) ?>') no-repeat center center fixed; background-size:cover; color:#fff;}
body {display:flex; flex-direction:column; background-color: rgba(0,0,0,0.85); background-blend-mode: overlay;}
.container {flex:1;}
.navbar-custom {position: sticky; top:0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index:1000;}
.profile-pic {width:50px; height:50px; border-radius:50%; overflow:hidden;}
.profile-pic img {width:100%; height:100%; object-fit:cover;}
.show-header {
    display: flex;
    gap: 30px; /* spacing between image and table */
    align-items: flex-start;
}

.show-header img {
    width: 100%; /* full width of column */
    max-width: 250px; /* optional fixed max size */
    height: auto;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.6);
    background-color: rgba(255, 255, 255, 0.05);
    padding: 5px;
    flex-shrink: 0; /* prevent image from shrinking */
}

.show-header .flex-fill {
    flex: 1; /* table area fills remaining space */
}
.table-container {max-height:500px; overflow-y:auto;}
.ep-table img {height:80px; object-fit:fit; border-radius:4px;}
.table th, .table td {vertical-align: middle;}
footer {background:#000;text-align:center;padding:1rem;margin-top:auto;}
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

<div class="container py-4"><a href="tv.php" class="btn btn-outline-primary mb-3 mt-2">Back to TV Shows</a>
    <div class="show-header">
        <div>
            <img src="<?= htmlspecialchars($cover) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($folder) ?>">
			
        </div>
        <div class="flex-fill">
            <h1><?= htmlspecialchars($folder) ?> - Episodes</h1>
			<p class="mb-2"><strong>Genres:</strong> <?= htmlspecialchars($genre) ?></p>
			<p><strong>Overview:</strong> <?= htmlspecialchars($overview) ?></p>
            
            <div class="table-container">
                <table class="table table-dark table-striped ep-table mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Season</th>
                            <th>Episode</th>
                            <th>Play</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($episodes as $ep): ?>
                        <tr>
                            <td><?= htmlspecialchars($ep['title']) ?></td>
                            <td><?= $ep['season'] ?: '-' ?></td>
                            <td><?= $ep['episode'] ?: '-' ?></td>
                            <td>
                                <button class="btn btn-success btn-sm play-btn" data-short="<?= htmlspecialchars($ep['short_url']) ?>">Play</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Video Modal -->
<div class="modal fade" id="playerModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title text-white">Now Playing</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <video id="player" class="w-100" controls playsinline></video>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modalEl = document.getElementById('playerModal');
const player = document.getElementById('player');
const modal = new bootstrap.Modal(modalEl);

document.querySelectorAll('.play-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        player.src = 'stream.php?s=' + encodeURIComponent(btn.dataset.short);

        // Force autoplay to be allowed
        player.muted = true;
        player.load();

        // Start playback immediately in the same user gesture
        player.play().then(() => {
            // Now unmute instantly within the same gesture
            player.muted = false;
        }).catch(err => {
            console.log("Play blocked:", err);
        });

        // Open the modal AFTER starting playback
        modal.show();
    });
});
</script>

<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>