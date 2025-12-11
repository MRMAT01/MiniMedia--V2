<?php
// You can log attempts here if you want
// file_put_contents('access_log.txt', date('c') . " Tried folder access: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// Send 302 or 301 as needed
http_response_code(302);

// Send user somewhere else (homepage, error page, custom message)
header("Location: ../404.php"); // change as needed
exit;
?>
