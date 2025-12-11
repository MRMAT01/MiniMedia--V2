<?php
/**
 * Progress Tracking API
 * Track and retrieve video playback progress
 */

require '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$mediaId = (int)($_POST['media_id'] ?? $_GET['media_id'] ?? 0);
$position = (int)($_POST['position'] ?? 0);
$duration = (int)($_POST['duration'] ?? 0);

try {
    if ($action === 'update') {
        // Update playback progress
        if ($duration <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid duration']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO playback_progress (user_id, media_id, position, duration)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                position = ?,
                duration = ?,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $mediaId,
            $position,
            $duration,
            $position,
            $duration
        ]);
        
        $percentage = round(($position / $duration) * 100, 2);
        
        echo json_encode([
            'success' => true,
            'position' => $position,
            'duration' => $duration,
            'percentage' => $percentage
        ]);
        
    } elseif ($action === 'get') {
        // Get playback progress
        $stmt = $pdo->prepare("
            SELECT position, duration, percentage, updated_at
            FROM playback_progress
            WHERE user_id = ? AND media_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $mediaId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            echo json_encode([
                'success' => true,
                'has_progress' => true,
                'position' => (int)$progress['position'],
                'duration' => (int)$progress['duration'],
                'percentage' => (float)$progress['percentage'],
                'updated_at' => $progress['updated_at']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_progress' => false,
                'position' => 0,
                'duration' => 0,
                'percentage' => 0
            ]);
        }
        
    } elseif ($action === 'list') {
        // Get continue watching list (5-95% progress)
        $limit = (int)($_GET['limit'] ?? 10);
        
        $stmt = $pdo->prepare("
            SELECT m.*, p.position, p.duration, p.percentage, p.updated_at as progress_updated
            FROM playback_progress p
            JOIN media m ON p.media_id = m.id
            WHERE p.user_id = ? AND p.percentage > 5 AND p.percentage < 95
            ORDER BY p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'count' => count($items)
        ]);
        
    } elseif ($action === 'delete') {
        // Remove progress (mark as complete/reset)
        $stmt = $pdo->prepare("
            DELETE FROM playback_progress
            WHERE user_id = ? AND media_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $mediaId]);
        
        echo json_encode(['success' => true]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    logError('Progress API error', [
        'action' => $action,
        'media_id' => $mediaId,
        'error' => $e->getMessage()
    ]);
    
    echo json_encode(['success' => false, 'error' => 'Operation failed']);
}
