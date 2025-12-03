<?php
// core/rate_limit.php - very simple IP-based rate limiter using temp files
function rate_limit_check($key, $limit = 5, $window_seconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $dir = __DIR__ . '/../storage/ratelimit';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $file = "$dir/" . md5($key . '|' . $ip);
    $now = time();
    $data = ['count'=>0,'start'=>$now];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = @json_decode($raw, true) ?: $data;
        if ($now - $data['start'] > $window_seconds) {
            $data = ['count'=>0,'start'=>$now];
        }
    }
    $data['count'] += 1;
    file_put_contents($file, json_encode($data));
    return $data['count'] <= $limit;
}
