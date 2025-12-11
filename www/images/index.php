<!DOCTYPE html>
<html>
<head>
    <title>MiniMedia - 404</title>
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
        <a class="navbar-brand" href="../index.php"><img src="<?= getLogoPath() ?>" height="60" alt="MiniMedia"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link active" aria-current="page" href="../index.php">Home</a>
            </li>

          </ul>
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0 profile-menu"> 
            <li class="nav-item dropdown">
              <a class="nav-link" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="profile-pic">
                    <img src="../profile/1756602294_guest.jpg" alt="Profile">
                 </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="../login.php"><i class="fas fa-sign-in-alt fa-fw"></i> Login</a></li>
                <li><a class="dropdown-item" href="../register.php"><i class="fas fa-sign-out-alt fa-fw"></i> Register</a></li>
              </ul>
            </li>
         </ul>
        </div>
      </div>
    </nav>

<div class="container py-4">
<div style="margin: 0 auto;padding: 30px 10px;position:relative;width:800px;">
<!-- Start Html5Video BODY section -->
<div style="position:relative;width:800px;height:600px;">
<video controls="controls" autoplay="autoplay" poster="../404/404.jpg" width="800" height="600">
<source src="../404/404.mp4" type="video/mp4" />
<source src="../404/404.webm" type="video/webm" />
</video>
</div>
	</div>
		</div>
<!-- Footer -->
<footer class="bg-dark py-3 text-center text-light mt-4">
  <p>&copy; <?= date('Y') ?> MiniMedia. All rights reserved.</p>
  <p>Designed by <a href="#" target="_blank">MrMat</a></p>
</footer>
</body>
</html>