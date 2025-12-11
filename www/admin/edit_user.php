<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Handle profile image upload
    $profile_image = $user['profile_image'];
    if (!empty($_FILES['profile_image']['name'])) {
        $filename = time() . "_" . basename($_FILES['profile_image']['name']);
        $target = "../profile/" . $filename;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $target);
        $profile_image = "profile/" . $filename; // Store relative path for DB
    }

    $update = $pdo->prepare("UPDATE users SET username=?, email=?, role=?, profile_image=? WHERE id=?");
    $update->execute([$username, $email, $role, $profile_image, $id]);

    header("Location: users.php");
    exit();
}

// Fetch admin user for sidebar
$stmt = $pdo->prepare("SELECT role, username, profile_image FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$usr = $stmt->fetch(PDO::FETCH_ASSOC);
$profileImage = (!empty($usr['profile_image']) && file_exists('../'.$usr['profile_image'])) ? '../'.$usr['profile_image'] : "../profile/guest.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Edit User</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {
    height: 100%; 
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
<div class="container mt-5">
    <h2>Edit User</h2>
    <form method="post" enctype="multipart/form-data" class="bg-white p-4 rounded shadow text-dark">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Profile Image</label><br>
            <img src="<?= $user['profile_image'] ? '../'.$user['profile_image'] : '../profile/guest.png' ?>" width="60" class="rounded-circle mb-2"><br>
            <input type="file" name="profile_image" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Save</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </form>
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
