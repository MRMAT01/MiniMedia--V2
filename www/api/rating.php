<?php
/**
 * Rating API
 * Add/Update/Get ratings
 */

require '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$mediaId = (int)($_POST['media_id'] ?? $_GET['media_id'] ?? 0);
$mediaType = $_POST['media_type'] ?? $_GET['media_type'] ?? 'media';
$rating = (int)($_POST['rating'] ?? 0);

try {
    if ($action === 'rate') {
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Rating must be between 1-5']);
            exit;
        }
        
        $col = $mediaType === 'music' ? 'music_id' : 'media_id';
        
        // Insert or update rating
        $stmt = $pdo->prepare("
            INSERT INTO ratings (user_id, $col, media_type, rating)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType, $rating, $rating]);
        
        // Get average rating
        $stmt = $pdo->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count
            FROM ratings
            WHERE $col = ? AND media_type = ?
        ");
        $stmt->execute([$mediaId, $mediaType]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'user_rating' => $rating,
            'avg_rating' => round($stats['avg_rating'], 1),
            'total_ratings' => $stats['count']
        ]);
        
    } elseif ($action === 'get') {
        // Get ratings for media
        $col = $mediaType === 'music' ? 'music_id' : 'media_id';
        
        // Get average stats
        $stmt = $pdo->prepare("
            SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_ratings,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM ratings
            WHERE $col = ? AND media_type = ?
        ");
        $stmt->execute([$mediaId, $mediaType]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user's rating
        $stmt = $pdo->prepare("
            SELECT rating FROM ratings
            WHERE user_id = ? AND $col = ? AND media_type = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
        $userRating = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'user_rating' => $userRating ? (int)$userRating : null,
            'avg_rating' => $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0,
            'total_ratings' => (int)$stats['total_ratings'],
            'distribution' => [
                5 => (int)$stats['five_star'],
                4 => (int)$stats['four_star'],
                3 => (int)$stats['three_star'],
                2 => (int)$stats['two_star'],
                1 => (int)$stats['one_star']
            ]
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    logError('Rating API error', [
        'action' => $action,
        'media_id' => $mediaId,
        'error' => $e->getMessage()
    ]);
    
    echo json_encode(['success' => false, 'error' => 'Operation failed']);
}
