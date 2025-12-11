<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = "member"; // default role

    // Handle profile image upload
    $profileImage = "profile/guest.png";
    if (!empty($_FILES['profile_image']['name'])) {
        $targetDir = "profile/";
        $targetFile = $targetDir . time() . "_" . basename($_FILES["profile_image"]["name"]);
        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile);
        $profileImage = $targetFile;
    }

    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $error = "Email already in use. <a href='register.php'>Try again</a>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_image) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $password, $role, $profileImage])) {
            $_SESSION['message'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit;
        } else {
            $error = "Registration failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MiniMedia - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js'></script>
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
footer{background:#000;text-align:center;padding:1rem;margin-top:auto;}	
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
<div class="card shadow p-4" style="width:600px;">
    <h2>Register</h2>
    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username" required>
        </div>
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email" required>
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <div class="mb-3">
            <input type="file" name="profile_image" class="form-control">
        </div>
        <button class="btn btn-primary">Register</button>
    </form>
    <p class="mt-3">Already registered? <a href="login.php">Login here</a></p>
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