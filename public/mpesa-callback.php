<?php
// public/mpesa-callback.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mpesa.php';

$body = file_get_contents('php://input');
file_put_contents(__DIR__ . '/../storage/logs/mpesa.log', date('c') . " CALLBACK: " . $body . PHP_EOL, FILE_APPEND);

// Parse JSON
$data = json_decode($body, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Invalid payload']);
    exit;
}

// Record callback into DB and update payment status
$ok = mpesa_record_callback($data);

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
