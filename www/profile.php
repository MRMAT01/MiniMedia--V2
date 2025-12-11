<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Admin check ---
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role, username, profile_image FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$usr = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$usr || $usr['role']!=='admin'){
    echo "<h1 style='color:#fff;text-align:center;margin-top:20%'>Access denied</h1>";
    exit;
}
$profileImage = (!empty($usr['profile_image']) && file_exists($usr['profile_image'])) ? $usr['profile_image'] : "profile/guest.png";

$user_id = $_SESSION['user_id'];
$success = $error = "";

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newUsername = trim($_POST['username']);
    $newPassword = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : $user['password'];
    $profileImage = $user['profile_image'];

    // Handle image upload
if (!empty($_FILES['profile_image']['name']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    $targetDir = "profile/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $targetFile = $targetDir . time() . "_" . basename($_FILES["profile_image"]["name"]);

    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile)) {
        // Delete old image if it exists and is not the default guest
        if (!empty($profileImage) && file_exists($profileImage) && basename($profileImage) !== 'guest.png') {
            @unlink($profileImage);
        }

        $profileImage = $targetFile;
    }
}

    try {
    $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, profile_image=? WHERE id=?");
    $stmt->execute([$newUsername, $newPassword, $profileImage, $user_id]);

    // Update session immediately
    $_SESSION['username'] = $newUsername;
    $_SESSION['profile_image'] = $profileImage;

    // Also update local $user array for the form display
    $user['username'] = $newUsername;
    $user['profile_image'] = $profileImage;

    $success = "Profile updated successfully!";
} catch (Exception $e) {
    $error = "Update failed. Username might already exist.";
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
html, body {height:100%; margin:0; background:#111; color:#fff;}
body {display:flex; flex-direction:column;}
.container {flex:1;}
.navbar-custom {position:sticky; top:0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index:1000;}
.profile-pic {width:50px; height:50px; border-radius:50%; overflow:hidden;}
.profile-pic img {width:100%; height:100%; object-fit:cover;}
.container {
    flex: 1; /* this makes the content area grow and push footer down */
}
.navbar-custom {
    position: sticky;
    top: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
    z-index: 1000;
}
.toggle-change::after { border-top:0; border-bottom:0.3em solid; }
.card-img-top { height: 350px; object-fit: cover; border-radius: 8px; }
footer{background:#000;text-align:center;padding:1rem;margin-top:auto;}
</style>
</head>
<body class="bg-dark">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><img src="<?= getLogoPath() ?>" height="45" alt="MiniMedia"></a>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="btn btn-sm btn-success me-2" href="index.php">Home</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="home.php?type=movie">Movies</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="tv.php?type=tv">TV</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2" href="music.php?type=music">Music</a></li>
		<li class="nav-item"><a class="btn btn-sm btn-dark me-2 active" href="featured.php?type=featured">Featured</a></li>
      </ul>
	<div class="ms-auto">
      <div class="dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center text-light" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
          <div class="profile-pic me-2"><img src="<?=htmlspecialchars($profileImage)?>" alt="Profile"></div>
          <span><?=htmlspecialchars($usr['username'])?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
          <li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="register.php"><i class="fas fa-sign-in-alt fa-fw"></i> Register</a></li>
				<li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt fa-fw"></i> Login</a></li>
				<li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Log Out</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div class="container py-5 text-white">
    <h2>My Profile</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Profile Picture</label><br>
            <img src="<?= htmlspecialchars($user['profile_image'] ?: 'profile/guest.png') ?>" width="100" class="rounded-circle mb-2"><br>
            <input type="file" name="profile_image" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<script>
// Profile dropdown toggle visual
document.querySelectorAll('.dropdown-toggle').forEach(item => {
  item.addEventListener('click', event => {
    if(event.target.classList.contains('dropdown-toggle') || event.target.parentElement.classList.contains('dropdown-toggle')){
      event.target.classList.toggle('toggle-change');
    }
  });
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
