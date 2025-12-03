<?php
// Simple redirect to public folder
$uri = $_SERVER['REQUEST_URI'];
if (strpos($uri, '/api/') === 0 || strpos($uri, '/mpesa-callback.php') !== false) {
    // Let web server route to public/api
}
header('Location: /public/');
exit;
