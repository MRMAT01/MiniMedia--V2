<?php
// admin/admin_upload.php
ob_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.txt');

try {
    require __DIR__ . '/../config.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id'])) throw new Exception('Forbidden');

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role || $role['role'] !== 'admin') throw new Exception('Forbidden');

    // Inputs
    $chunk = $_FILES['chunk'] ?? null;
    $mediaFile = $_FILES['media_file'] ?? null;
    $coverUpload = $_FILES['cover'] ?? null;
    $filename = $_POST['filename'] ?? ($mediaFile['name'] ?? '');
    $offset = (int)($_POST['offset'] ?? 0);
    $done = (isset($_POST['done']) && (int)$_POST['done'] === 1);

    if (!$filename) throw new Exception('No filename provided');

    // temp .part folder
    $tmpDir = defined('MEDIA_CACHE_DIR') ? MEDIA_CACHE_DIR : __DIR__ . '/../media_cache';
    @mkdir($tmpDir, 0777, true);
    $filenameSafeTemp = preg_replace('/[^A-Za-z0-9 _\-.]/', '_', $filename);
    $destPart = rtrim($tmpDir, '/\\') . '/' . $filenameSafeTemp . '.part';

    // --- chunk upload ---
    if ($chunk) {
        $data = file_get_contents($chunk['tmp_name']);
        if ($data === false) throw new Exception('Failed to read chunk');
        if (file_put_contents($destPart, $data, FILE_APPEND) === false) throw new Exception('Failed to append chunk');
        if ($offset === 0 && $coverUpload && !empty($coverUpload['tmp_name'])) {
            @move_uploaded_file($coverUpload['tmp_name'], $tmpDir . '/' . $filenameSafeTemp . '.cover');
        }
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // --- direct single-file ---
    if ($mediaFile && !$done) {
        if (!move_uploaded_file($mediaFile['tmp_name'], $destPart)) throw new Exception('Failed to store uploaded file part');
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // --- finalize assembly ---
    if ($done) {
        $type = validateMediaType($_POST['type'] ?? 'movie');
        $baseName = preg_replace('/\.(mp4|mkv|avi|mov|flv|wmv|ts|mp3|m4a)$/i', '', $filename);

        // patterns to clean from filename/folder
        $removePatterns = [
            '/\[[^\]]+\]/', '/\([^\)]+\)/',
            '/\b(5|5.1|4k|2160p|1080p|720p|480p|HDR|HDR10|DV|DDP5|WEB-?DL|WEBRip|WEB|BluRay|BRRip|BDRip|DVDRip|HDTV|HDTS|CAM|TS|TC|x264|x265|h264|hevc|10bit|[0-9]{1,4}MB|[0-9]{1,2}GB|NF|AMZN|HULU|MAX|UHD|HDRip|PROPER|REPACK|LIMITED|EXTENDED|UNRATED|YTS|RARBG|RGB|EVO|P2P|AAC|AC5|Rapta|BONE|RMTeam|WORKPRINT|COLLECTiVE|sylix|MeGusta|EZTVx|to|FENiX|ETHEL|nhtfs|ELiTE|ATVP|NTb|successfulcrab|CRAV|SNAKE|SYNCOPY|JFF|AFG|FGT|FLEET|Galaxy(RG|TV)?|RIP|Ganool|YIFY|EVO|P2P|U2|NeoNoir|TGx|MX|AM|RARBG|ETRG|Eng|ESub|DD(5|1)?|LAMA|Teema|Sagrona|XviD)\b/i',
            '/[_\.]+/'
        ];

        $cleanForFolder = function($s) use ($removePatterns) {
            $s = preg_replace($removePatterns, ' ', $s);
            $s = preg_replace('/[^A-Za-z0-9 _-]/','',$s);
            $s = trim(preg_replace('/\s+/',' ',$s));
            return $s ?: 'unknown';
        };

        $seasonNum = null; $episodeNum = null; $showName = null;

        if ($type === 'tv') {
    if (preg_match('/(.+?)[\s._-]+S(\d{1,2})E(\d{1,2})/i', $baseName, $m)) {
        $showNameRaw = $m[1];
        $showName = preg_replace($removePatterns, ' ', $showNameRaw);
        $showName = preg_replace('/\b(19|20)\d{2}\b/', '', $showName); // remove year
        $showName = trim(preg_replace('/\s+/', ' ', $showName));

        $seasonNum = (int)$m[2];
        $episodeNum = (int)$m[3];

        $finalNameSafe = $showName . ' S' . str_pad($seasonNum,2,'0',STR_PAD_LEFT) .
                         'E' . str_pad($episodeNum,2,'0',STR_PAD_LEFT) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
    } else {
        $showName = preg_replace($removePatterns, ' ', $baseName);
        $showName = preg_replace('/\b(19|20)\d{2}\b/', '', $showName); // remove year
        $showName = trim(preg_replace('/\s+/', ' ', $showName));

        $seasonNum = 1;
        $episodeNum = null;
        $finalNameSafe = $showName . '.' . pathinfo($filename, PATHINFO_EXTENSION);
    }

    // TV target folder
    $showFolder = rtrim(defined('MEDIA_TV_DIR') ? MEDIA_TV_DIR : __DIR__ . '/../tv', '/\\') . '/' . $showName;
    @mkdir($showFolder, 0777, true);

    $seasonFolder = $showFolder . '/Season ' . str_pad($seasonNum,2,'0',STR_PAD_LEFT);
    @mkdir($seasonFolder, 0777, true);

    // Final media file
    $finalFile = $seasonFolder . '/' . $finalNameSafe;

    // Media cache folder
    $mediaCacheFolder = rtrim($tmpDir, '/\\') . '/' . $showName . '/Season ' . str_pad($seasonNum,2,'0',STR_PAD_LEFT);
    @mkdir($mediaCacheFolder, 0777, true);

    // JSON metadata path
    $metaFs = $mediaCacheFolder . '/' . pathinfo($finalNameSafe, PATHINFO_FILENAME) . '.json';
	} else {
            // Movie/featured/music
            $cleanTitle = $cleanForFolder($baseName);
            if ($type==='movie') $targetDir = rtrim(defined('MEDIA_MOVIES_DIR') ? MEDIA_MOVIES_DIR : __DIR__ . '/../movies', '/\\');
            elseif ($type==='featured') $targetDir = rtrim(defined('MEDIA_FEATURED_DIR') ? MEDIA_FEATURED_DIR : __DIR__ . '/../featured', '/\\');
            else $targetDir = rtrim(defined('MEDIA_MUSIC_DIR') ? MEDIA_MUSIC_DIR : __DIR__ . '/../music', '/\\');

            $mediaCacheFolder = rtrim($tmpDir, '/\\') . '/' . $cleanTitle;
            @mkdir($targetDir, 0777, true);
            @mkdir($mediaCacheFolder, 0777, true);

            $finalNameSafe = $cleanTitle . '.' . pathinfo($filename, PATHINFO_EXTENSION);
            $finalFile = rtrim($targetDir, '/\\') . '/' . $finalNameSafe;
            $metaFs = $mediaCacheFolder . '/' . $cleanTitle . '.json';
        }

        if (!file_exists($destPart)) throw new Exception('Assembled part file not found');

        // Try rename, fallback to copy
        if (!@rename($destPart, $finalFile)) {
            if (!@copy($destPart, $finalFile)) throw new Exception('Failed to move final media file');
        }

        // Ensure part file deleted
        if (file_exists($destPart)) @unlink($destPart);

        // Cover handling
        $tempCover = $tmpDir . '/' . $filenameSafeTemp . '.cover';
        if ($coverUpload && !empty($coverUpload['tmp_name'])) @move_uploaded_file($coverUpload['tmp_name'], $mediaCacheFolder . '/cover.jpg');
        elseif (file_exists($tempCover)) @rename($tempCover, $mediaCacheFolder . '/cover.jpg');

        $coverFs = $mediaCacheFolder . '/cover.jpg';
        $backdropFs = $mediaCacheFolder . '/backdrop.jpg';

        // ---------- TMDb fetch ----------
        $tmdb_api_key = $tmdb_api ?? '';
        if ($tmdb_api_key && in_array($type, ['movie','tv'])) {
            $searchTitle = ($type==='tv') ? $showName : ($cleanTitle ?? $baseName);
            $searchTitle = trim(preg_replace('/S\d{1,2}E\d{1,2}/i','',$searchTitle));
            $endpoint = ($type==='tv') ? 'search/tv' : 'search/movie';
            $searchUrl = "https://api.themoviedb.org/3/{$endpoint}?api_key=".urlencode($tmdb_api_key)."&query=".urlencode($searchTitle);
            if ($type==='movie' && preg_match('/\b(19|20)\d{2}\b/',$baseName,$ym)) $searchUrl .= '&year='.urlencode($ym[0]);
            $searchJson = @file_get_contents($searchUrl);
            $search = @json_decode($searchJson,true);
            if (!empty($search['results'][0]['id'])) {
                $tmdbId = $search['results'][0]['id'];
                $detailUrl = "https://api.themoviedb.org/3/".($type==='tv'?'tv':'movie')."/{$tmdbId}?api_key=".urlencode($tmdb_api_key);
                $detailJson = @file_get_contents($detailUrl);
                $detail = @json_decode($detailJson,true);
                if (is_array($detail)) {
                    @file_put_contents($metaFs,json_encode($detail,JSON_PRETTY_PRINT));
                    if (!empty($detail['poster_path'])) {
                        $posterData = @file_get_contents('https://image.tmdb.org/t/p/w500'.$detail['poster_path']);
                        if ($posterData !== false) @file_put_contents($coverFs,$posterData);
                    }
                    if (!empty($detail['backdrop_path'])) {
                        $backdropData = @file_get_contents('https://image.tmdb.org/t/p/w1280'.$detail['backdrop_path']);
                        if ($backdropData !== false) @file_put_contents($backdropFs,$backdropData);
                    }
                }
            }
        }

        // ---------- FFmpeg fallback ----------
        if ((!file_exists($coverFs)||filesize($coverFs)===0||!file_exists($backdropFs)||filesize($backdropFs)===0)
            && defined('FFMPEG_PATH') && FFMPEG_PATH && function_exists('exec')) {
            if (!file_exists($coverFs)||filesize($coverFs)===0) {
                @exec(escapeshellarg(FFMPEG_PATH).' -y -i '.escapeshellarg($finalFile).' -vf scale=300:-1 -vframes 1 '.escapeshellarg($coverFs).' 2>&1');
            }
            if (!file_exists($backdropFs)||filesize($backdropFs)===0) {
                @exec(escapeshellarg(FFMPEG_PATH).' -y -i '.escapeshellarg($finalFile).' -vf scale=1280:-1 -vframes 1 '.escapeshellarg($backdropFs).' 2>&1');
            }
        }

        // ---------- fallback images ----------
        $fallbackCover = $tmpDir.'/noimage.png';
        $fallbackBackdrop = $tmpDir.'/no_backdrop.png';
        if (!file_exists($coverFs) && file_exists($fallbackCover)) @copy($fallbackCover,$coverFs);
        if (!file_exists($backdropFs) && file_exists($fallbackBackdrop)) @copy($fallbackBackdrop,$backdropFs);

        // relative paths for DB
        $projectRoot = realpath(__DIR__.'/..');
        $makeRel = function($abs) use ($projectRoot) {
            $abs = str_replace('\\','/',$abs);
            $projectRoot = str_replace('\\','/',$projectRoot);
            if (strpos($abs,$projectRoot)===0) return ltrim(substr($abs,strlen($projectRoot)),'/\\');
            return str_replace('\\','/',$abs);
        };
        $pathRel = $makeRel($finalFile);
        $coverRel = $makeRel($coverFs);
        $backdropRel = $makeRel($backdropFs);

        // Insert DB
        $short_url = substr(md5(time().rand()),0,8);
        $stmt = $pdo->prepare("INSERT INTO media (title,type,path,cover,backdrop,short_url,season,episode,created_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $titleToInsert = ($type==='tv') ? ($showName ?? $cleanForFolder($baseName)) : ($cleanTitle ?? $cleanForFolder($baseName));
        $stmt->execute([$titleToInsert,$type,$pathRel,$coverRel,$backdropRel,$short_url,$seasonNum,$episodeNum,date('Y-m-d H:i:s')]);

        // assign default category
        $mediaId = $pdo->lastInsertId();
        $map = ['movie'=>6,'featured'=>8,'tv'=>5,'music'=>null];
        if (isset($map[$type]) && $map[$type]!==null) $pdo->prepare("INSERT INTO media_categories (media_id, category_id) VALUES (?,?)")->execute([$mediaId,$map[$type]]);

        ob_end_clean();
        echo json_encode(['success'=>true,'message'=>'Upload complete']);
        exit;
    }

    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'No action performed']);
    exit;

} catch(Exception $e) {
    error_log('admin_upload error: '.$e->getMessage());
    @file_put_contents(__DIR__ . '/../logs/php_error.txt','['.date('Y-m-d H:i:s').'] '.$e->getMessage().PHP_EOL,FILE_APPEND);
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}