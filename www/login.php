<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Login success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower($user['role']); // force lowercase for safety

        if ($_SESSION['role'] === 'admin') {
            header("Location: admin/index.php?page=dashboard");
            exit();
        } else {
            header("Location: home.php");
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
		html, body {
	height: 100%; /* full height */
    margin: 0;
    background-image: url('images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #fff;
}
body {
    display: flex;
    flex-direction: column;
}
.container {
    flex: 1; /* this makes the content area grow and push footer down */
}
footer {
    margin-top: auto; /* ensures footer sticks to bottom */
}
		/* Sticky translucent navbar */
        .navbar-custom {
            position: sticky;
            top: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }
        .category-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-top:2rem; }
        .category-card { text-align:center; cursor:pointer; transition:transform .2s; }
        .category-card:hover { transform:scale(1.05); }
        .category-card img { width:100%; height:150px; object-fit:cover; border-radius:8px; }
        .profile-img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
        .search-input { width:250px; max-width:100%; }
		
		 /* Profile Picture */
    .profile-pic{
       display: inline-block;
       vertical-align: middle;
        width: 50px;
        height: 50px;
        overflow: hidden;
       border-radius: 50%;
    }
     
    .profile-pic img{
       width: 100%;
       height: auto;
       object-fit: cover;
    }
    .profile-menu .dropdown-menu {
      right: 0;
      left: unset;
    }
    .profile-menu .fa-fw {
      margin-right: 10px;
    }
     
    .toggle-change::after {
      border-top: 0;
      border-bottom: 0.3em solid;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><img src="<?= getLogoPath() ?>" height="60" alt="MiniMedia"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link active" aria-current="page" href="index.php">Home</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>
<div class="container py-4 d-flex align-items-center justify-content-center" style="height:100vh;">
    <div class="card shadow p-4" style="width:350px;">
        <h3 class="mb-3 text-center">Login</h3>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3">
            <a href="register.php">Register</a>
        </div>
    </div>
	</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer -->
<footer class="py-3 text-center text-light">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="admin/index.php">MrMat</a></p>
</footer>
</body>
</html>