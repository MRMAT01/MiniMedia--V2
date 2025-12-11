<?php
require 'config.php';
session_start();
if(!isset($_SESSION['user_id'])) exit(json_encode(['stopped'=>false]));

// Admin check
$stmt=$pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$role=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$role || $role['role']!=='admin') exit(json_encode(['stopped'=>false]));

$filename = $_GET['file'] ?? '';
if(!$filename) exit(json_encode(['stopped'=>false]));

$tmpDir = __DIR__.'/uploads_tmp';
$filenameSafe = preg_replace('/[^A-Za-z0-9 _-]/','',$filename);

// Check JSON for PID
$progressFile = $tmpDir.'/'.$filenameSafe.'.progress.json';
if(file_exists($progressFile)){
    $data = json_decode(file_get_contents($progressFile), true);
    if(!empty($data['pid'])){
        $pid = (int)$data['pid'];
        if($pid){
            // Kill process (Windows)
            exec("taskkill /F /PID $pid 2>NUL");
        }
    }
}

// Cleanup temp files
@unlink($tmpDir.'/'.$filenameSafe.'.part');
@unlink($tmpDir.'/'.$filenameSafe.'.progress.json');
@unlink($tmpDir.'/'.$filenameSafe.'.log');

echo json_encode(['stopped'=>true]);

?>