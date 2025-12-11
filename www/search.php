<?php
/**
 * Global Search Endpoint
 * Searches across all media types
 */

require 'config.php';
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');

// Minimum 3 characters for search
if (strlen($query) < 3) {
    echo json_encode(['results' => []]);
    exit;
}

$like = "%$query%";
$results = [];

try {
    // Search media (movies, TV shows, featured)
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            title, 
            type, 
            cover, 
            short_url,
            season,
            episode,
            created_at
        FROM media
        WHERE title LIKE ?
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$like]);
    $mediaResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add media to results
    foreach ($mediaResults as $item) {
        $results[] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'cover' => $item['cover'] ?: 'images/noimage.png',
            'short_url' => $item['short_url'],
            'subtitle' => $item['type'] === 'tv' ? "Season {$item['season']} Episode {$item['episode']}" : ucfirst($item['type'])
        ];
    }
    
    // Search music
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            title, 
            artist, 
            album,
            cover, 
            short_url,
            created_at
        FROM music
        WHERE title LIKE ? OR artist LIKE ? OR album LIKE ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);
    $musicResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add music to results
    foreach ($musicResults as $item) {
        $results[] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'type' => 'music',
            'cover' => $item['cover'] ?: 'images/noimage.png',
            'short_url' => $item['short_url'],
            'subtitle' => $item['artist'] . ($item['album'] ? ' • ' . $item['album'] : '')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    logError('Search error', ['query' => $query, 'error' => $e->getMessage()]);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'results' => []
    ]);
}
