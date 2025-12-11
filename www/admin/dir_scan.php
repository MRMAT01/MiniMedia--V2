<?php
// dir_scan.php - batch-import media from import/ using same logic as admin_upload.php
error_reporting(E_ALL);
ini_set('display_errors',0);
ini_set('log_errors',1);
ini_set('error_log', __DIR__.'/../logs/php_error.txt');
header('Content-Type: application/json');

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin check
if(!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error'=>'Forbidden']);
    exit;
}
$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);
    $clean = preg_replace('/(\[.*?\]|\(.*?\)|\b(720p|1080p|480p|2160p|WEBRip|WEB|BluRay|BRRip|HDRip|HDTV|AMZN|NF|MAX|x264|x265|HEVC|10bit|FENiX|XviD|EZTVx\.to|EZTVx|AFG|YTS|RARBG|PROPER|REPACK|AAC|DDP5|WEB-DL|DVDRip|NTb|MeGusta|GalaxyTV|ELiTE)\b)/i','',$baseName);
    $clean = preg_replace('/[\[\]\(\)\._-]+/',' ',$clean);
    $clean = trim(preg_replace('/\s+/',' ',$clean));
    // remove remaining unsafe chars for folder name
    $clean = preg_replace('/[^A-Za-z0-9 _-]/','',$clean);
    $clean = trim($clean);
    return $clean ?: $baseName;
}

// helper: detect season+episode from filename
function detect_season_episode($name) {
    // common patterns: S02E01, s2e1, 2x01, Season 2 Episode 1, S2E1
    if (preg_match('/S(?:eason)?\s*0?(\d{1,2})\s*[Eex]\s*0?(\d{1,2})/i', $name, $m)) {
        return [ (int)$m[1], (int)$m[2] ];
    }
    if (preg_match('/\b0?(\d{1,2})x0?(\d{1,2})\b/i', $name, $m)) {
        return [ (int)$m[1], (int)$m[2] ];
    }
    if (preg_match('/S0?(\d{1,2})E0?(\d{1,2})/i', $name, $m)) {
        return [ (int)$m[1], (int)$m[2] ];
    }
    // "Season 2 Episode 3"
    if (preg_match('/Season\s*0?(\d{1,2}).*Episode\s*0?(\d{1,2})/i', $name, $m)) {
        return [ (int)$m[1], (int)$m[2] ];
    }
    return [null,null];
}

// helper: filesystem -> web-relative forward-slash path
function rel_path($fsPath) {
    $root = realpath(__DIR__ . '/../');
    $p = str_replace('\\','/', ltrim(str_replace($root, '', $fsPath), '/\\'));
    return $p ?: null;
}

// recursively scan import dir for files
$files = [];
if (is_dir($importDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($importDir));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (in_array($ext, array_merge($allowedVideo,$allowedAudio))) {
            $files[] = $f->getPathname();
        }
    }
}

$results = [];
if (empty($files)) {
    echo json_encode(['results'=>[],'message'=>'No media files found in import folder']);
    exit;
}

// process each file
foreach ($files as $filePath) {
    $resItem = ['source' => $filePath, 'status' => 'pending'];
    try {
        $baseName = pathinfo($filePath, PATHINFO_BASENAME);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isAudio = in_array($ext, $allowedAudio);
        $isVideo = in_array($ext, $allowedVideo);

        // determine type: music if audio, tv if SxxExx in filename, else movie
        $type = 'movie';
        if ($isAudio) $type = 'music';
        else {
            [$sDetect, $eDetect] = detect_season_episode($baseName);
            if ($sDetect !== null && $eDetect !== null) $type = 'tv';
        }

        // normalize title/clean folder name
        $cleanName = normalize_clean($baseName);
        // For TV, attempt to derive show folder name by removing SxxExx etc.
        $tvFolderName = $cleanName;
        if ($type === 'tv') {
            // remove season/episode tokens
            $tvFolderName = preg_replace('/S\d{1,2}E\d{1,2}/i','',$tvFolderName);
            $tvFolderName = preg_replace('/\b\d+x\d+\b/i','',$tvFolderName);
            $tvFolderName = trim($tvFolderName);
            if ($tvFolderName === '') $tvFolderName = $cleanName;
        }

        // extract year if present in title
        $year = null;
        if (preg_match('/\b(19|20)\d{2}\b/', $cleanName, $m)) {
            $year = $m[0];
            $title = trim(str_replace($year,'',$cleanName));
        } else {
            $title = $cleanName;
        }

        $safeFolderName = preg_replace('/[^A-Za-z0-9 _-]/','',$tvFolderName ?: $title ?: pathinfo($baseName, PATHINFO_FILENAME));
        $filenameSafe = preg_replace('/[^A-Za-z0-9 _-]/','',$title ?: pathinfo($baseName, PATHINFO_FILENAME));

        // prepare target dirs and filenames (mirrors admin_upload.php)
        if ($type === 'tv') {
            // determine season & episode
            [$season, $episode] = detect_season_episode($baseName);
            $season = $season ?? ($_GET['season'] ?? 1);
            $episode = $episode ?? ($_GET['episode'] ?? 1);

            $targetDir = __DIR__ . '/../tv/' . $safeFolderName . '/Season ' . $season;
            $mediaCacheFolder = __DIR__ . '/../media_cache/' . $safeFolderName;
            $seasonFolder = $mediaCacheFolder . '/Season ' . $season;
            @mkdir($targetDir,0777,true);
            @mkdir($mediaCacheFolder,0777,true);
            @mkdir($seasonFolder,0777,true);

            $coverFileFs    = $mediaCacheFolder . DIRECTORY_SEPARATOR . 'cover.jpg';
            $backdropFileFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . 'backdrop.jpg';
            $mainMetadataFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . $filenameSafe . '.json';

            $epNum = is_numeric($episode) ? str_pad((int)$episode,2,'0',STR_PAD_LEFT) : ($episode ?: '01');
            $seasonNum = is_numeric($season) ? str_pad((int)$season,2,'0',STR_PAD_LEFT) : ($season ?: '01');
            $episodeMetadataFs = $seasonFolder . DIRECTORY_SEPARATOR . 'S'.$seasonNum.'E'.$epNum.'.json';
			
		} 	elseif ($type === 'movie') {
			$targetDir = __DIR__ . '/../movies';
			@mkdir($targetDir, 0777, true);

			$mediaCacheFolder = __DIR__ . '/../media_cache/' . $filenameSafe;
			@mkdir($mediaCacheFolder, 0777, true);

			// Both poster & backdrop in the same media_cache/<movie_name> folder
			$coverFileFs    = $mediaCacheFolder . DIRECTORY_SEPARATOR . 'cover.jpg';
			$backdropFileFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . 'backdrop.jpg';
			$mainMetadataFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . $filenameSafe . '.json';
			
		}	else { // music
            $targetDir = __DIR__ . '/../music';
            @mkdir($targetDir,0777,true);
            $mediaCacheFolder = __DIR__ . '/../media_cache/' . $filenameSafe;
            @mkdir($mediaCacheFolder,0777,true);
            $coverFileFs = $targetDir . DIRECTORY_SEPARATOR . 'covers' . DIRECTORY_SEPARATOR . $filenameSafe . '.jpg';
            @mkdir(dirname($coverFileFs),0777,true);
            $backdropFileFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . 'backdrop.jpg';
            $mainMetadataFs = $mediaCacheFolder . DIRECTORY_SEPARATOR . $filenameSafe . '.json';
        }

        // final filename
        $extOut = $type === 'music' ? '.mp3' : '.mp4';
        $finalFileName = $filenameSafe . ($year ? " $year" : '') . $extOut;
        $finalFile = $targetDir . DIRECTORY_SEPARATOR . $finalFileName;

        // run ffmpeg conversion if final ext differs or source not mp4/mp3 (and always convert to chosen encoding)
        $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filenameSafe . '.scan.log';
        $cmd = '';
        if ($type === 'music') {
            $cmd = "\"{$ffmpeg}\" -y -i " . escapeshellarg($filePath) . " -b:a 192k " . escapeshellarg($finalFile) . " 2> " . escapeshellarg($logFile);
        } else {
            // convert to mp4/h264 + 720p
            $cmd = "\"{$ffmpeg}\" -y -i " . escapeshellarg($filePath) . " -c:v libx264 -preset fast -crf 23 -vf scale=-2:720 -c:a aac " . escapeshellarg($finalFile) . " 2> " . escapeshellarg($logFile);
        }

        // execute conversion
        $resItem['cmd'] = $cmd;
        exec($cmd, $out, $rc);
        $resItem['ffmpeg_rc'] = $rc;

        if (!file_exists($finalFile)) {
            // if conversion failed but original is already mp4/mp3 and allowed, try to move original
            if (($type !== 'music' && strtolower($ext)==='mp4') || ($type==='music' && strtolower($ext)==='mp3')) {
                @copy($filePath, $finalFile);
            }
        }

        if (!file_exists($finalFile)) {
            $resItem['status'] = 'error';
            $resItem['error'] = "Conversion failed, final file not created. See log: {$logFile}";
            $results[] = $resItem;
            // don't delete original; move on
            continue;
        }

        // attempt TMDb fetch (movies/tv)
        if ($type !== 'music') {
            $needCover = !file_exists($coverFileFs) || filesize($coverFileFs) === 0;
            $needBackdrop = !file_exists($backdropFileFs) || filesize($backdropFileFs) === 0;
            $needMetadata = !file_exists($mainMetadataFs) || filesize($mainMetadataFs) === 0;

            if (($needCover || $needBackdrop || $needMetadata) && $tmdb_api) {
                // prepare search title (strip SxxExx if tv)
                $searchTitle = $title;
                if ($type === 'tv') $searchTitle = preg_replace('/S\d{1,2}E\d{1,2}/i','',$searchTitle);

                $searchUrl = "https://api.themoviedb.org/3/" . ($type==='tv' ? 'search/tv' : 'search/movie') . "?api_key=" . urlencode($tmdb_api) . "&query=" . urlencode($searchTitle);
                if ($year && $type==='movie') $searchUrl .= "&year=" . urlencode($year);

                $searchJson = @file_get_contents($searchUrl);
                $search = @json_decode($searchJson, true);
                if (is_array($search) && !empty($search['results'][0]['id'])) {
                    $tmdbId = $search['results'][0]['id'];
                    $detailUrl = "https://api.themoviedb.org/3/" . ($type==='tv' ? 'tv' : 'movie') . "/{$tmdbId}?api_key=" . urlencode($tmdb_api);
                    $detailJson = @file_get_contents($detailUrl);
                    $detail = @json_decode($detailJson, true);

                    if (is_array($detail)) {
                        // write metadata
                        @file_put_contents($mainMetadataFs, json_encode($detail, JSON_PRETTY_PRINT));

                        if ($needCover && !empty($detail['poster_path'])) {
                            $posterUrl = 'https://image.tmdb.org/t/p/original'.$detail['poster_path'];
                            $posterData = @file_get_contents($posterUrl);
                            if ($posterData !== false) @file_put_contents($coverFileFs, $posterData);
                        }
                        if ($needBackdrop && !empty($detail['backdrop_path'])) {
                            $backdropUrl = 'https://image.tmdb.org/t/p/original'.$detail['backdrop_path'];
                            $backdropData = @file_get_contents($backdropUrl);
                            if ($backdropData !== false) @file_put_contents($backdropFileFs, $backdropData);
                        }

                        // TV: fetch specific episode info if we have season/episode
                        if ($type === 'tv' && is_numeric($season) && is_numeric($episode)) {
                            $epUrl = "https://api.themoviedb.org/3/tv/{$tmdbId}/season/".intval($season)."/episode/".intval($episode)."?api_key=".urlencode($tmdb_api);
                            $epJson = @file_get_contents($epUrl);
                            $epData = @json_decode($epJson, true);
                            if (is_array($epData) && !empty($epData)) {
                                @file_put_contents($episodeMetadataFs, json_encode($epData, JSON_PRETTY_PRINT));
                            } else {
                                // fallback
                                if (!empty($detail['last_episode_to_air'])) {
                                    @file_put_contents($episodeMetadataFs, json_encode($detail['last_episode_to_air'], JSON_PRETTY_PRINT));
                                }
                            }
                        } elseif ($type === 'tv') {
                            // create season folder and optionally a placeholder if no ep-specific info
                            if (!file_exists($episodeMetadataFs) && !empty($detail['last_episode_to_air'])) {
                                @file_put_contents($episodeMetadataFs, json_encode($detail['last_episode_to_air'], JSON_PRETTY_PRINT));
                            }
                        }
                    }
                } else {
                    // no results
                    // do nothing; fallback copy below
                }
            }
        }

        // fallback cover/backdrop
        $fallbackCover = __DIR__ . '/../media_cache/noimage.png';
        $fallbackBackdrop = __DIR__ . '/../media_cache/no_backdrop.png';
        if (!file_exists($coverFileFs) && file_exists($fallbackCover)) copy($fallbackCover, $coverFileFs);
        if (!file_exists($backdropFileFs) && file_exists($fallbackBackdrop)) copy($fallbackBackdrop, $backdropFileFs);

        // compute DB relative paths with forward slashes
        $dbPath = ($type==='tv')
            ? 'tv/' . str_replace('\\','/', $safeFolderName) . '/Season ' . $season . '/' . $finalFileName
            : (($type==='movie') ? 'movies/' . $finalFileName : 'music/' . $finalFileName);

        $coverRel = file_exists($coverFileFs) ? rel_path($coverFileFs) : 'media_cache/noimage.png';
        $backdropRel = file_exists($backdropFileFs) ? rel_path($backdropFileFs) : 'media_cache/no_backdrop.png';

        // insert into DB
        $short_url = substr(md5(time().rand()),0,8);
        $stmt = $pdo->prepare("INSERT INTO media (title,type,path,cover,short_url,season,episode,created_at,backdrop) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $title,
            $type,
            $dbPath,
            $coverRel,
            $short_url,
            ($type==='tv' ? $season : null),
            ($type==='tv' ? $episode : null),
            date('Y-m-d H:i:s'),
            $backdropRel
        ]);

        // cleanup: remove original imported file
        @unlink($filePath);

        $resItem['status'] = 'success';
        $resItem['title'] = $title;
        $resItem['type'] = $type;
        $resItem['db_path'] = $dbPath;
        $resItem['short_url'] = $short_url;
    } catch (Throwable $e) {
        $resItem['status'] = 'error';
        $resItem['error'] = $e->getMessage();
        error_log("scan.php error processing {$filePath}: ".$e->getMessage());
    }
    $results[] = $resItem;
}

// final JSON
echo json_encode(['results' => $results], JSON_PRETTY_PRINT);
exit;
?>
```