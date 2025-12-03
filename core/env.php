<?php
// core/env.php - simple dotenv loader
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($k,$v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (!getenv($k)) putenv("$k=$v");
        if (!defined($k)) define($k, $v);
    }
}
