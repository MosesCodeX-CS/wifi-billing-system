<?php
// core/mikrotik.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/routeros_api.class.php';

function create_hotspot_user($code, $plan = '24hrs') {
    $password = $code;
    $ok = create_hotspot_user_on_router($code, $password, $plan);
    if ($ok) return true;

    // Fallback: log and return false so the calling code can still persist voucher and notify admin
    file_put_contents(__DIR__ . '/../storage/logs/mpesa.log', date('c') . " ROUTER PUSH FAILED for $code (plan=$plan)\n", FILE_APPEND);
    return false;
}
