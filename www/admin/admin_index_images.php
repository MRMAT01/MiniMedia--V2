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

// Default images mapping
$defaultImages = [
    1 => 'images/movies_default.png',
    2 => 'images/tv_default.png',
    3 => 'images/music_default.png',
    4 => 'images/featured_default.png',
];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Delete action
    if (isset($_POST['delete']) && $_POST['delete']==1) {
        $stmt = $pdo->prepare("SELECT image FROM index_images WHERE id=?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cat && file_exists(__DIR__ . '/../' . $cat['image']) && !in_array($cat['image'], $defaultImages)) {
            unlink(__DIR__ . '/../' . $cat['image']);
        }

        $stmt = $pdo->prepare("UPDATE index_images SET image = ? WHERE id=?");
        $stmt->execute([$defaultImages[$id], $id]);
        echo "<div class='alert alert-success'>Image deleted, reverted to default.</div>";
    }

    // Upload action
    if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image_upload'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        $categoryNames = [
            1 => 'movies',
            2 => 'tv',
            3 => 'music',
            4 => 'other'
        ];

        $uploadDir = __DIR__ . '/../images/index_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = $uploadDir . $categoryNames[$id] . "_" . time() . "." . $ext;

        if (move_uploaded_file($file['tmp_name'], $filename)) {
            // Store relative path for HTML
            $relativePath = 'images/index_images/' . $categoryNames[$id] . "_" . time() . "." . $ext;
            $stmt = $pdo->prepare("UPDATE index_images SET image = ? WHERE id = ?");
            $stmt->execute([$relativePath, $id]);

            echo "<div class='alert alert-success'>Image uploaded successfully!</div>";
        } else {
            echo "<div class='alert alert-danger'>Failed to move uploaded file.</div>";
        }
    }
}

// Fetch categories
$stmt = $pdo->query("SELECT * FROM index_images ORDER BY id ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Index Images</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {
	height: 100%; /* full height */
    margin: 0;
    background-image: url('../images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #fff;
}
body {
    display: flex;
    flex-direction: column;
}
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
/* Layout spacing */
.page-content {
    padding-top: 90px; /* distance below fixed navbar */
    padding-bottom: 80px; 
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
<div class="container py-5 text-light">
    <h2>Manage Index Images</h2>
	<p>Just delete image to get default back.</p>
    <table class="table table-striped table-bordered text-dark">
        <thead>
            <tr>
                <th>Category</th>
                <th>Default Image</th>
                <th>Current Image</th>
                <th>Upload / Replace</th>
                <th>Delete Current</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($categories as $cat): 
                $current = (!empty($cat['image']) && file_exists(__DIR__ . '/../' . $cat['image'])) ? $cat['image'] : $defaultImages[$cat['id']];
            ?>
            <tr>
                <td><?= htmlspecialchars($cat['name']) ?></td>
                <td><img src="../<?= htmlspecialchars($defaultImages[$cat['id']]) ?>" height="50"></td>
                <td><img src="../<?= htmlspecialchars($current) ?>" height="50"></td>
                <td>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="file" name="image_upload" accept="image/*" required>
                        <button type="submit" class="btn btn-sm btn-success mt-1">Upload</button>
                    </form>
                </td>
                <td>
                    <?php if($current != $defaultImages[$cat['id']]): ?>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="hidden" name="delete" value="1">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted">Default</span>
                    <?php endif; ?>
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