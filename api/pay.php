<?php
// api/pay.php - initiate STK push and create pending payment row
try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/mpesa.php';

    header('Content-Type: application/json; charset=utf-8');
    
    $phone = trim($_POST['phone'] ?? '');
    $plan = trim($_POST['plan'] ?? '');

    $prices = [
      '2hrs' => 10,
      '6hrs' => 30,
      '12hrs' => 50,
      '24hrs' => 80,
      '1week' => 300
    ];

    $dur_map = [
      '2hrs' => 7200,
      '6hrs' => 21600,
      '12hrs' => 43200,
      '24hrs' => 86400,
      '1week' => 604800
    ];

    if (!isset($prices[$plan])) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'invalid plan']);
      exit;
    }

    // Normalize and validate phone number (accepts: 07XXXXXXXX, 2547XXXXXXXX, +254712345678, etc.)
    if (!is_valid_phone($phone)) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'invalid phone - use format: 0712345678 or 254712345678']);
      exit;
    }
    $phone = normalize_phone($phone);

    $amount = $prices[$plan];

    // Create a pending payment row with null checkout id first
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO payments (checkout_request_id, merchant_request_id, phone, amount, status, raw_callback) VALUES (NULL, NULL, ?, ?, "PENDING", NULL)');
    $stmt->execute([$phone, $amount]);
    $payment_id = $pdo->lastInsertId();

    // Initiate STK push
    $resp = mpesa_stk_push($phone, $amount, 'BellamyHotspot');

    // Log
    file_put_contents(__DIR__ . '/../storage/logs/mpesa.log', date('c') . " STK_RESP: " . json_encode($resp) . PHP_EOL, FILE_APPEND);

    if (!$resp || !is_array($resp)) {
      http_response_code(500);
      echo json_encode(['status'=>'error','msg'=>'stk push failed - no response']);
      exit;
    }

    // Expected response contains CheckoutRequestID on success
    if (isset($resp['ResponseCode']) && $resp['ResponseCode'] === '0' && isset($resp['CheckoutRequestID'])) {
      $checkout = $resp['CheckoutRequestID'];
      // update payment row with checkout id
      $stmt = $pdo->prepare('UPDATE payments SET checkout_request_id = ? WHERE id = ?');
      $stmt->execute([$checkout, $payment_id]);
      http_response_code(200);
      echo json_encode(['status'=>'pending','checkoutRequestID'=>$checkout]);
    } else {
      // store raw error into payment
      $stmt = $pdo->prepare('UPDATE payments SET raw_callback = ? WHERE id = ?');
      $stmt->execute([json_encode($resp), $payment_id]);
      http_response_code(400);
      $msg = $resp['ResponseDescription'] ?? $resp['msg'] ?? 'Unknown error';
      echo json_encode(['status'=>'error','msg'=>$msg,'raw'=>$resp]);
    }
} catch (Exception $e) {
    error_log('pay.php error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','msg'=>'Server error: ' . $e->getMessage()]);
}
