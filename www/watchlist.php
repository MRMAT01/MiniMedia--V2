<?php
/**
 * Watchlist Page
 * Display user's watchlist
 */

require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profileImage = (!empty($user['profile_image']) && file_exists($user['profile_image'])) 
    ? $user['profile_image'] 
    : "profile/guest.png";

// Get watchlist items
$stmt = $pdo->prepare("
    SELECT m.id, m.title, m.type, m.season, m.episode,
           m.cover, m.short_url, m.created_at, m.backdrop,
           w.added_at, 'media' as item_type
    FROM watchlist w
    JOIN media m ON w.media_id = m.id
    WHERE w.user_id = ? AND w.media_type IN ('movie', 'tv', 'featured')
    
    ORDER BY added_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$watchlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Watchlist - MiniMedia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <link rel="stylesheet" href="assets/css/enhancements.css">
    <style>
        body {
            background: url('images/bg.jpg') fixed no-repeat;
            background-size: cover;
            min-height: 100vh;
            color: #fff;
        }
        
        .container {
            padding-top: 30px;
            padding-bottom: 60px;
        }
        
        .page-header {
            background: rgba(0, 0, 0, 0.7);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .watchlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }
        
        .watchlist-item {
            position: relative;
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .watchlist-item:hover {
            transform: translateY(-8px);
        }
        
        .watchlist-item img {
            width: 100%;
            height: 270px;
            object-fit: cover;
        }
        
        .watchlist-item-info {
            padding: 15px;
        }
        
        .watchlist-item-info h6 {
            margin: 0 0 5px;
            font-size: 0.9rem;
        }
        
        .watchlist-item-info small {
            color: #999;
        }
        
        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 68, 68, 0.9);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10;
        }
        
        .remove-btn:hover {
            background: #ff0000;
            transform: scale(1.1);
        }
        
        .empty-watchlist {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-watchlist i {
            font-size: 4rem;
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-heart"></i> My Watchlist</h1>
                    <p class="mb-0 text-muted"><?= count($watchlistItems) ?> item<?= count($watchlistItems) != 1 ? 's' : '' ?> saved</p>
                </div>
                <a href="home.php" class="btn btn-outline-light">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
        
        <?php if (empty($watchlistItems)): ?>
            <div class="empty-watchlist">
                <i class="far fa-heart"></i>
                <h3>Your watchlist is empty</h3>
                <p class="text-muted">Start adding movies, TV shows, and music to your watchlist!</p>
                <a href="home.php" class="btn btn-primary mt-3">
                    <i class="fas fa-search"></i> Browse Media
                </a>
            </div>
        <?php else: ?>
            <div class="watchlist-grid">
                <?php foreach ($watchlistItems as $item): ?>
                    <div class="watchlist-item" data-item-id="<?= $item['id'] ?>" data-item-type="<?= $item['item_type'] ?>">
                        <button class="remove-btn" onclick="removeFromWatchlist(<?= $item['id'] ?>, '<?= $item['item_type'] ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <a href="<?= $item['item_type'] === 'music' ? 'music.php' : 'featured_media.php?s=' . $item['short_url'] ?>">
                            <img src="<?= $item['cover'] ?: 'images/noimage.png' ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                        </a>
                        
                        <div class="watchlist-item-info">
                            <h6><?= htmlspecialchars($item['title']) ?></h6>
                            <small>
                                <?php if ($item['item_type'] === 'music'): ?>
                                    <?= htmlspecialchars($item['season']) ?>
                                <?php else: ?>
                                    <?= ucfirst($item['type']) ?>
                                    <?php if ($item['type'] === 'tv'): ?>
                                        • S<?= $item['season'] ?>E<?= $item['episode'] ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </small>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="far fa-clock"></i> 
                                    Added <?= date('M j, Y', strtotime($item['added_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/toast.js"></script>
    <script>
        async function removeFromWatchlist(mediaId, mediaType) {
            try {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('media_id', mediaId);
                formData.append('media_type', mediaType);
                
                const response = await fetch('api/watchlist.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove item from page
                    const item = document.querySelector(`[data-item-id="${mediaId}"][data-item-type="${mediaType}"]`);
                    if (item) {
                        item.style.animation = 'slideOut 0.3s ease';
                        setTimeout(() => {
                            item.remove();
                            
                            // Check if watchlist is now empty
                            const remaining = document.querySelectorAll('.watchlist-item').length;
                            if (remaining === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    
                    ToastManager.success('Removed from watchlist');
                } else {
                    ToastManager.error('Failed to remove item');
                }
            } catch (error) {
                console.error('Remove error:', error);
                ToastManager.error('Failed to remove item');
            }
        }
    </script>
</body>
</html>
