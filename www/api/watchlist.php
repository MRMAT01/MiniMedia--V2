<?php
/**
 * Watchlist API
 * Add/Remove/Check items in user's watchlist
 */

require '../config.php';
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$mediaId = (int)($_POST['media_id'] ?? 0);
$mediaType = $_POST['media_type'] ?? 'movie';

// Validate inputs
if (!in_array($action, ['add', 'remove', 'check', 'list'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if (!in_array($mediaType, ['movie', 'tv', 'featured', 'music'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid media type']);
    exit;
}

try {
    if ($action === 'add') {
        // Add to watchlist - all types use media_id column
        $stmt = $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, media_id, media_type) VALUES (?, ?, ?)");
        $result = $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'in_watchlist' => true
        ]);
        
    } elseif ($action === 'remove') {
        // Remove from watchlist - all types use media_id column
        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND media_id = ? AND media_type = ?");
        $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
        
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'in_watchlist' => false
        ]);
        
    } elseif ($action === 'check') {
        // Check if in watchlist - all types use media_id column
        $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND media_id = ? AND media_type = ?");
        $stmt->execute([$_SESSION['user_id'], $mediaId, $mediaType]);
        $exists = $stmt->fetch() !== false;
        
        echo json_encode([
            'success' => true,
            'in_watchlist' => $exists
        ]);
        
    } elseif ($action === 'list') {
        // Get user's watchlist
        $limit = (int)($_GET['limit'] ?? 50);
        
        // Get media items
        $stmt = $pdo->prepare("
            SELECT m.*, w.added_at, 'media' as item_type
            FROM watchlist w
            JOIN media m ON w.media_id = m.id
            WHERE w.user_id = ? AND w.media_type = 'media'
            ORDER BY w.added_at DESC
            LIMIT ?
        ");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        $mediaItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get music items
        $stmt = $pdo->prepare("
            SELECT m.*, w.added_at, 'music' as item_type
            FROM watchlist w
            JOIN music m ON w.music_id = m.id
            WHERE w.user_id = ? AND w.media_type = 'music'
            ORDER BY w.added_at DESC
            LIMIT ?
        ");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        $musicItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort
        $allItems = array_merge($mediaItems, $musicItems);
        usort($allItems, function($a, $b) {
            return strtotime($b['added_at']) - strtotime($a['added_at']);
        });
        
        echo json_encode([
            'success' => true,
            'items' => array_slice($allItems, 0, $limit),
            'count' => count($allItems)
        ]);
    }
    
} catch (Exception $e) {
    logError('Watchlist API error', [
        'action' => $action,
        'media_id' => $mediaId,
        'error' => $e->getMessage()
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
    ]);
}
