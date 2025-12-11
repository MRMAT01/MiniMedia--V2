<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Handle adding category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
    }
    header("Location: admin_categories.php");
    exit;
}

// Handle delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM media_categories WHERE category_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
    header("Location: admin_categories.php");
    exit;
}

// Fetch all categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all media
$media = $pdo->query("SELECT * FROM media ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

// Handle assigning media to category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $category_id = (int)$_POST['category_id'];
    $media_id = (int)$_POST['media_id'];

    if ($category_id > 0 && $media_id > 0) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO media_categories (media_id, category_id) VALUES (?, ?)");
        $stmt->execute([$media_id, $category_id]);
    }
    header("Location: admin_categories.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Categories</title>
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
footer{background:#000;text-align:center;padding:1rem;margin-top:auto;}
</style>
</head>
<body>
<!-- Sidebar -->
<div id="sidebar">
  <div class="logo">
    <img src="<?= getLogoPath() ?>" alt="MiniMedia"></div>
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

    <h1 class="mb-4">Manage Categories</h1>

    <!-- Add Category -->
    <div class="card bg-secondary mb-4">
        <div class="card-body">
            <form method="post">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input type="text" name="name" class="form-control" placeholder="New Category Name" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="new_category" class="btn btn-success w-100">Add Category</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- Categories List -->
    <div class="card bg-secondary">
        <div class="card-body">
            <h4>Existing Categories</h4>
            <ul class="list-group list-group-flush">
                <?php foreach ($categories as $cat): ?>
                    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($cat['name']) ?>
                        <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger">Delete</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
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