<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Admin index - fixed
 * - Loads only selected media type (movies / tv / music / featured)
 * - Dashboard groups TV shows into single cards and shows seasons/episode counts
 * - TV library groups episodes similarly
 * - Uses MEDIA_* constants from config.php with safe fallbacks
 * - Keeps layout & styles unchanged
 */

/* ---------- Paths (safe fallbacks) ---------- */
$baseDir    = __DIR__ . '/..';
$cover_path = defined('MEDIA_COVER_DIR') ? MEDIA_COVER_DIR : $baseDir . '/covers';
$music_path = defined('MEDIA_MUSIC_DIR') ? MEDIA_MUSIC_DIR : $baseDir . '/music';
$tv_path    = defined('MEDIA_TV_DIR') ? MEDIA_TV_DIR : $baseDir . '/tv';
$movie_path = defined('MEDIA_MOVIES_DIR') ? MEDIA_MOVIES_DIR : $baseDir . '/movies';
$cache_dir  = defined('MEDIA_CACHE_DIR') ? MEDIA_CACHE_DIR : $baseDir . '/media_cache';
$featured_path = defined('MEDIA_FEATURED_DIR') ? MEDIA_FEATURED_DIR : $baseDir . '/featured';

/* ---------- Admin check ---------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT role, username, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usr || $usr['role'] !== 'admin') {
    echo "<h1 style='color:#fff;text-align:center;margin-top:20%'>Access denied</h1>";
    exit;
}
$profileImage = (!empty($usr['profile_image']) && file_exists(__DIR__ . '/../' . $usr['profile_image'])) ? '../' . $usr['profile_image'] : "../profile/guest.png";

/* ---------- Page / type selection ---------- */
$page = $_GET['page'] ?? 'movies';
$type = match ($page) {
    'movies'   => 'movie',
    'tv'       => 'tv',
    'music'    => 'music',
    'featured' => 'featured',
    default    => 'movie',
};

/* ---------- Messages ---------- */
$msg = $_GET['msg'] ?? '';

/* ---------- Helpers ---------- */

/**
 * Normalize path for display: returns web-friendly relative path from project root (www/)
 * If DB stores relative paths already, this will return them unchanged.
 */
function webPath($path) {
    if (!$path) return '';
    $p = str_replace('\\', '/', $path);
    // If it already looks relative (no drive letter / not absolute), return as-is
    if (strpos($p, ':') === false && $p[0] !== '/') return $p;
    // Otherwise, strip up to www/ or project root
    $parts = preg_split('#/www/#i', $p);
    if (count($parts) > 1) return end($parts);
    // fallback: attempt to strip realpath(__DIR__.'/..')
    $base = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    if (strpos($p, $base) === 0) {
        $rel = ltrim(substr($p, strlen($base)), '/\\');
        return $rel;
    }
    return $p;
}

/**
 * Extract show name from a path (robust): expects tv/ShowName/Season X/filename
 * Fallback: use title.
 */
function extractShowNameFromPath($path, $titleFallback = 'Unknown Show') {
    if (empty($path)) return $titleFallback;
    $p = str_replace('\\', '/', $path);
    // remove leading possible './' or '/'
    $p = preg_replace('#^\./#', '', $p);
    $parts = array_values(array_filter(explode('/', $p)));
    // find "tv" segment if present and take next segment as show name
    $lowerParts = array_map('strtolower', $parts);
    $tvIndex = array_search('tv', $lowerParts, true);
    if ($tvIndex !== false && isset($parts[$tvIndex + 1])) {
        return $parts[$tvIndex + 1];
    }
    // fallback heuristic: if length >=3 take third-from-end as ShowName
    if (count($parts) >= 3) {
        return $parts[count($parts) - 3];
    }
    // last fallback: title
    return $titleFallback;
}

/* ---------- Fetch data depending on $type ---------- */
if ($type === 'music') {
    // Fetch music from media table
    $stmt = $pdo->prepare("SELECT * FROM media WHERE type = ? ORDER BY title");
    $stmt->execute(['music']);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // movies, tv, featured from media table
    $stmt = $pdo->prepare("SELECT * FROM media WHERE type = ? ORDER BY title, season, episode");
    $stmt->execute([$type]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Fetch recent uploads for dashboard (only when dashboard view) ---------- */
$recentRaw = [];
if ($page === 'dashboard') {
    // union media + music if music table exists else only media
    try {
        $recentRaw = $pdo->query("
            SELECT id, title, type, created_at, cover, path, season, episode FROM media
            UNION ALL
            SELECT id, title, 'music' as type, created_at, cover, NULL as path, NULL as season, NULL as episode FROM music
            ORDER BY created_at DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // fallback (no music table)
        $recentRaw = $pdo->query("SELECT id, title, type, created_at, cover, path, season, episode FROM media ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    }

    // group TV into shows with seasons count
    $tvShows = [];
    $otherMedia = [];

    foreach ($recentRaw as $r) {
        if ($r['type'] === 'tv') {
            $showName = extractShowNameFromPath($r['path'], $r['title']);
            if (!isset($tvShows[$showName])) {
                $tvShows[$showName] = [
                    'cover' => $r['cover'] ?: null,
                    'seasons' => [],
                    'created_at' => $r['created_at']
                ];
            }
            $seasonNum = intval($r['season']) ?: 1;
            $tvShows[$showName]['seasons'][$seasonNum] = ($tvShows[$showName]['seasons'][$seasonNum] ?? 0) + 1;
            if ($r['created_at'] > $tvShows[$showName]['created_at']) {
                $tvShows[$showName]['created_at'] = $r['created_at'];
            }
        } else {
            $otherMedia[] = $r;
        }
    }

    $allRecent = [];
    foreach ($tvShows as $name => $data) {
        $allRecent[] = [
            'type' => 'tv_show',
            'name' => $name,
            'cover' => $data['cover'],
            'seasons' => $data['seasons'],
            'created_at' => $data['created_at']
        ];
    }
    foreach ($otherMedia as $m) {
        $allRecent[] = [
            'type' => $m['type'],
            'name' => $m['title'],
            'cover' => $m['cover'],
            'created_at' => $m['created_at']
        ];
    }

    usort($allRecent, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $allRecent = array_slice($allRecent, 0, 8);
}

/* ---------- Build grouped TV library data (for TV page) ---------- */
$tvShowsLibrary = [];
if ($type === 'tv') {
    foreach ($media as $m) {
        // prefer a show name from path; fallback to title in DB
        $showName = extractShowNameFromPath($m['path'], $m['title']);
        $seasonNum = intval($m['season']) ?: 1;
        if (!isset($tvShowsLibrary[$showName])) {
            $tvShowsLibrary[$showName] = [
                'cover' => $m['cover'] ?: null,
                'seasons' => []
            ];
        }
        $tvShowsLibrary[$showName]['seasons'][$seasonNum] = ($tvShowsLibrary[$showName]['seasons'][$seasonNum] ?? 0) + 1;
        // keep most-recent cover if missing
        if (empty($tvShowsLibrary[$showName]['cover']) && !empty($m['cover'])) {
            $tvShowsLibrary[$showName]['cover'] = $m['cover'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body { height: 100%; margin: 320; background-image: url('../images/bg.jpg'); background-attachment: fixed; background-repeat: no-repeat; background-size: cover; color: #fff; }
body { display:flex; flex-direction:column; margin:0; font-family:sans-serif; background:#121212; color:#fff; }
#sidebar { position:fixed; top:0; left:0; bottom:0; width:250px; background:#1f1f1f; overflow-y:auto; padding:0; z-index:100; }
#sidebar .logo, #sidebar .profile { width:100%; text-align:center; padding:20px; border-bottom:1px solid #333; }
#sidebar .logo img { width:60%; max-width:100px; border-radius:8px; display:block; margin:0 auto 10px; }
#sidebar .btn-sidebar { width:100%; text-align:left; border:none; background:none; color:#ddd; padding:10px 20px; border-bottom:1px solid #333; }
#sidebar .btn-sidebar:hover { background:#333; color:#fff; }
header { position:fixed; left:250px; right:0; top:0; height:60px; background:#222; display:flex; justify-content:space-between; align-items:center; padding:0 20px; z-index:90; }
main { margin-left:250px; padding:80px 30px 30px 30px; }
.dashboard-tile {
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    background: #222 no-repeat center/cover;
    color: #fff;
    box-shadow: 0 0 15px rgba(0,0,0,0.4);
    transition: transform .2s, box-shadow .2s;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.dashboard-tile::after {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.80); /* dark overlay for text readability */
    border-radius: 12px;
}
.dashboard-tile h6, .dashboard-tile p {
    position: relative;
    z-index: 2;
}
.dashboard-tile:hover {
    transform: translateY(-4px);
    box-shadow: 0 0 15px rgba(255,255,255,0.2);
}
.media-grid { display:flex; flex-wrap:wrap; gap:15px; }
.media-card { width:160px; text-align:center; border-radius:10px; overflow:hidden; background:#222; transition:transform .2s; }
.media-card:hover { transform:scale(1.05); }
.media-card img { width:100%; height:220px; object-fit:cover; border-bottom:1px solid #444; }
.media-card span { display:block; margin-top:5px; color:#ccc; }
.footer { background:#000; text-align:center; padding:1rem; margin-top:auto; }
</style>
</head>
<body>
<!-- Sidebar -->
<div id="sidebar">
  <div class="logo">
    <a href="../index.php"><img src="<?= getLogoPath() ?>" alt="MiniMedia" ></a></div>
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
  <button class="btn-sidebar" onclick="window.location='../minidbadmin/index.php'"><i class="fas fa-image me-2"></i>MiniDBAdmin</button>
  <hr>
  <button class="btn-sidebar" onclick="window.location='users.php'"><i class="fas fa-users me-2"></i>Users</button>
</div>
<header>
  <h4>Admin Dashboard</h4> <?php if (!empty($msg)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?> 
  <div class="d-flex align-items-center gap-3">
  <div class="row g-3">
<!-- ===== MiniMedia Server Control Section ===== -->

  <div class="ms-auto">
    <strong>Status:</strong>
    <?php
      // Detect if port 8081 is in use (server running)
      exec('netstat -ano | find ":8081" | find "LISTENING"', $output);
      if (!empty($output)) {
          echo '<span style="color:limegreen;font-weight:bold;"> Running</span>';
      } else {
          echo '<span style="color:crimson;font-weight:bold;"> Stopped</span>';
      }
    ?>
  </div>
</div>
  </div>
</header>
<main>
<?php if($page==='dashboard'): ?>
<h3>Dashboard</h3>
<!--- Stats --->
<div class="row g-3 mb-4">
<?php
$stats = [
    ['title'=>'Total Movies',       'query'=>"SELECT COUNT(*) FROM media WHERE type='movie'"],
    ['title'=>'Total TV Episodes',  'query'=>"SELECT COUNT(*) FROM media WHERE type='tv'"],
    ['title'=>'Total Music',        'query'=>"SELECT COUNT(*) FROM music"],
	['title'=>'Total Featured',       'query'=>"SELECT COUNT(*) FROM media WHERE type='featured'"],
    ['title'=>'Total Categories',   'query'=>"SELECT COUNT(*) FROM categories"],
    ['title'=>'Total Users',        'query'=>"SELECT COUNT(*) FROM users"],
];

$bgImages = [
    '../images/bg_movies.png',
    '../images/bg_tv.png',
    '../images/bg_music.png',
	'../images/bg_featured.png',
    '../images/bg_categories.png',
    '../images/bg_users.png'
];

foreach($stats as $i => $s):
    $bg = $bgImages[$i] ?? '../images/default_bg.png';
?>
<div class="col-md-2 col-sm-6">
  <div class="dashboard-tile" style="background-image:url('<?= htmlspecialchars($bg) ?>')">
    <h6><?= htmlspecialchars($s['title']) ?></h6>
    <p class="fs-4 fw-bold"><?= $pdo->query($s['query'])->fetchColumn() ?></p>
  </div>
</div>
<?php endforeach; ?>
</div>
  <h5>Recent Uploads</h5>
  <div class="media-grid">
    <?php
    if (!empty($allRecent)):
      foreach ($allRecent as $r):
        $cover = (!empty($r['cover']) && file_exists(__DIR__ . '/../' . $r['cover'])) ? '../' . $r['cover'] : '../images/placeholder.png';
        if ($r['type'] === 'tv_show'): ?>
          <div class="media-card">
            <img src="<?=htmlspecialchars($cover)?>" alt="<?=htmlspecialchars($r['name'])?>">
            <span><strong><?=htmlspecialchars($r['name'])?></strong><br>
              <?php ksort($r['seasons']); foreach ($r['seasons'] as $season => $count) {
                  echo "S{$season}: {$count} eps<br>";
              } ?>
            </span>
          </div>
        <?php else: ?>
          <div class="media-card">
            <img src="<?=htmlspecialchars($cover)?>" alt="<?=htmlspecialchars($r['name'])?>">
            <span><?=htmlspecialchars($r['name'])?></span>
          </div>
        <?php endif;
      endforeach;
    else: ?>
      <div class="alert alert-secondary">No recent uploads found.</div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <h3><?= ucfirst($type) ?> Library</h3>

  <!-- Upload Button triggers modal -->
  <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-upload"></i> Upload <?= ucfirst($type) ?></button>

  <div class="media-grid">
    <?php
    if ($type === 'tv'):
      if (!empty($tvShowsLibrary)):
        foreach ($tvShowsLibrary as $showName => $data):
          $cover = (!empty($data['cover']) && file_exists(__DIR__ . '/../' . $data['cover'])) ? '../' . $data['cover'] : '../images/placeholder.png';
          ?>
          <div class="media-card">
            <img src="<?=htmlspecialchars($cover)?>" alt="<?=htmlspecialchars($showName)?>">
            <span><strong><?=htmlspecialchars($showName)?></strong><br>
              <?php ksort($data['seasons']); foreach ($data['seasons'] as $season => $count) {
                  echo "S{$season}: {$count} eps<br>";
              } ?>
            </span>
          </div>
        <?php endforeach;
      else: ?>
        <div class="alert alert-secondary">No TV shows found.</div>
      <?php endif;
    else:
  if ($type === 'music'):
    if (!empty($media)):
      foreach ($media as $m):
        $cover = (!empty($m['cover']) && file_exists(__DIR__ . '/../' . $m['cover'])) ? '../' . $m['cover'] : '../images/placeholder.png';
        $title = htmlspecialchars($m['title'] ?? $m['name'] ?? 'Untitled');
        ?>
        <div class="media-card">
          <img src="<?=htmlspecialchars($cover)?>" alt="<?= $title ?>">
          <span><?= $title ?></span>
        </div>
      <?php endforeach;
    else: ?>
      <div class="alert alert-secondary">No music found.</div>
    <?php endif;
  else:
    // movies (non-TV)
    if (!empty($media)):
      foreach ($media as $m):
        $cover = (!empty($m['cover']) && file_exists(__DIR__ . '/../' . $m['cover'])) ? '../' . $m['cover'] : '../images/placeholder.png';
        $title = htmlspecialchars($m['title'] ?? 'Untitled');
        ?>
        <div class="media-card">
          <img src="<?=htmlspecialchars($cover)?>" alt="<?= $title ?>">
          <span><?= $title ?></span>
        </div>
      <?php endforeach;
    else: ?>
      <div class="alert alert-secondary">No items found.</div>
    <?php endif;
  endif;
endif;

    ?>
  </div>
<?php endif; ?>
</main>

<!-- Upload Modal (kept as you provided) -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadModalTitle">Upload Media</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="mediaUploadForm" enctype="multipart/form-data" method="post">
          <div class="mb-2">
            <label>Media File</label>
            <input type="file" name="media_file" required class="form-control">
          </div>
          <div class="mb-2">
            <label>Cover (Optional)</label>
            <input type="file" name="cover" accept="image/*" class="form-control">
          </div>
          <div class="mb-2">
            <label>Media Type</label>
            <select name="type" id="mediaTypeSelect" class="form-select" required>
              <option value="movie">Movie</option>
              <option value="tv">TV</option>
              <option value="music">Music</option>
              <option value="featured">Featured</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success w-100">Upload & Convert</button>
        </form>

        <div id="uploadOverlay" style="display:none; text-align:center; margin-top:20px;">
          <div class="spinner-border text-light" role="status" style="width:4rem; height:4rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p>Uploading & processing media, please wait...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* Chunked uploader kept; simplified and defensive.
   Note: admin_upload.php still handles the server-side logic (chunking, finalize).
*/
const form = document.getElementById('mediaUploadForm');
const overlay = document.getElementById('uploadOverlay');
const typeSel = document.getElementById('mediaTypeSelect');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fileInput = form.querySelector('input[name="media_file"]');
    const file = fileInput?.files?.[0];
    if (!file) return alert('Please select a file');

    overlay.style.display = 'block';
    try {
        const chunkSize = 5 * 1024 * 1024;
        let offset = 0;
        const coverInput = form.querySelector('input[name="cover"]');
        const coverFile = coverInput?.files?.[0] ?? null;

        // upload chunks
        while (offset < file.size) {
            const chunk = file.slice(offset, offset + chunkSize);
            const fd = new FormData();
            fd.append('chunk', chunk);
            fd.append('filename', file.name);
            fd.append('offset', offset);
            fd.append('type', typeSel.value);
            // send cover only on first chunk (optional)
            if (offset === 0 && coverFile) fd.append('cover', coverFile);
            const res = await fetch('admin_upload.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) { overlay.style.display = 'none'; return alert(json.error || 'Chunk upload failed'); }
            offset += chunkSize;
        }

        // finalize
        const fdFinal = new FormData();
        fdFinal.append('filename', file.name);
        fdFinal.append('done', 1);
        fdFinal.append('type', typeSel.value);
        if (coverFile) fdFinal.append('cover', coverFile);

        const r = await fetch('admin_upload.php', { method: 'POST', body: fdFinal });
        const j = await r.json();
        overlay.style.display = 'none';
        if (!j.success) return alert(j.error || 'Upload finalize failed');
        location.reload();
    } catch (err) {
        overlay.style.display = 'none';
        alert('Upload error: ' + (err.message || err));
        console.error(err);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="index.php">MrMat</a></p>
</footer>
</body>
</html>