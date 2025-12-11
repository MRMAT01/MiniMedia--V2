<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED); // hide notices
ini_set('display_errors',0);
ini_set('log_errors',1);
ini_set('error_log', __DIR__.'/../logs/php_error.txt');                    // don't output errors to browser
header('Content-Type: application/json');           // always return JSON

require '../config.php';
if(session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

ob_start(); // buffer output

try {
    if(!isset($_SESSION['user_id'])){
        throw new Exception('Unauthorized', 403);
    }

    // Admin check
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$role || $role['role']!=='admin'){
        throw new Exception('Forbidden', 403);
    }

    // File check
    if(empty($_FILES['music_file']['tmp_name'])){
        http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No file uploaded']);
    exit;
}

    $tmpFile = $_FILES['music_file']['tmp_name'];
    $originalName = $_FILES['music_file']['name'];

    // --- Clean filename ---
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9 _-]/','',$name);
    $name = preg_replace('/\s+/',' ',trim($name));
    $filenameSafe = $name;

    // --- Target directories ---
    $targetDir = __DIR__.'/../music';
    @mkdir($targetDir,0777,true);
    $coverDir  = $targetDir.'/covers';
    @mkdir($coverDir,0777,true);

    $finalFile = $targetDir.'/'.$filenameSafe.'.mp3';

    // --- Convert to MP3 ---
    if(!isFFmpegAvailable()){
        throw new Exception('FFmpeg not found. Please install FFmpeg to enable music upload.');
    }

    $ffmpeg = FFMPEG_PATH;
    exec("\"$ffmpeg\" -y -i \"$tmpFile\" -ar 44100 -ac 2 -b:a 192k \"$finalFile\" 2>&1", $output, $return_var);
    if($return_var !== 0){
        error_log("FFmpeg error: ".implode("\n",$output));
        throw new Exception('Failed to convert file');
    }

    // --- Extract ID3 tags ---
    $title  = $filenameSafe;
    $artist = '';
    $album  = '';
    
    if(isGetID3Available()){
        require_once GETID3_PATH;
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze($finalFile);

        $title  = $fileInfo['tags']['id3v2']['title'][0] ?? $filenameSafe;
        $artist = $fileInfo['tags']['id3v2']['artist'][0] ?? '';
        $album  = $fileInfo['tags']['id3v2']['album'][0] ?? '';
        $coverData = $fileInfo['id3v2']['APIC'][0]['data'] ?? null;
    } else {
        $coverData = null;
    }

    // --- Cover image ---
    $coverFile = $coverDir.'/'.$filenameSafe.'.jpg';

    // Fallback Cover Art Archive
    if(empty($coverData) && $artist && $album){
        $query = urlencode("$artist $album");
        $json = @file_get_contents("https://coverartarchive.org/search?query=$query&format=json");
        if($json){
            $data = json_decode($json,true);
            if(!empty($data['images'][0]['image'])){
                $coverData = @file_get_contents($data['images'][0]['image']);
            }
        }
    }

    // Fallback iTunes
    if(empty($coverData) && $artist && $album){
        $query = urlencode("$artist $album");
        $json = @file_get_contents("https://itunes.apple.com/search?term=$query&entity=album&limit=1");
        if($json){
            $data = json_decode($json,true);
            if(!empty($data['results'][0]['artworkUrl100'])){
                $artworkUrl = str_replace('100x100','500x500',$data['results'][0]['artworkUrl100']);
                $coverData = @file_get_contents($artworkUrl);
            }
        }
    }

    if(!empty($coverData)){
        file_put_contents($coverFile,$coverData);
    } else {
        copy(__DIR__.'/../images/noimage.png',$coverFile);
    }

    // --- Short URL ---
    $short_url = substr(md5(time().rand()),0,8);

    // --- Insert into DB ---
    $stmt = $pdo->prepare("INSERT INTO music (title, artist, album, path, cover, short_url, created_at) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([
    $title,
    $artist,
    $album,
    str_replace(__DIR__.'/../','',$finalFile),
    str_replace(__DIR__.'/../','',$coverFile),
    $short_url,
    date('Y-m-d H:i:s')  // pass current timestamp from PHP
]);

    ob_end_clean();
    echo json_encode([
        'success'=>true,
        'title'=>$title,
        'artist'=>$artist,
        'album'=>$album,
        'cover'=>str_replace(__DIR__.'/../','',$coverFile),
        'short_url'=>$short_url
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
    exit;
}