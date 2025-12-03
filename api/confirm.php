<?php
// api/confirm.php - simple poll endpoint: check if payment with checkoutRequestID has succeeded
try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/mikrotik.php';

    header('Content-Type: application/json; charset=utf-8');

    $checkout = trim($_POST['checkoutRequestID'] ?? '');
    if (!$checkout) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'no checkoutRequestID']);
      exit;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE checkout_request_id = ?');
    $stmt->execute([$checkout]);
    $payment = $stmt->fetch();

    if (!$payment) {
      http_response_code(200);
      echo json_encode(['status'=>'pending']);
      exit;
    }

    if ($payment['status'] === 'SUCCESS') {
      // If already processed into voucher, check vouchers table
      $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE created_by_payment_id = ?');
      $stmt->execute([$payment['id']]);
      $v = $stmt->fetch();
      if ($v) {
        http_response_code(200);
        echo json_encode(['status'=>'success','voucher'=>$v['code']]);
        exit;
      }
      // Create voucher now
      $code = 'BELL' . strval(rand(10000,99999));
      // determine plan by amount
      $amount = floatval($payment['amount']);
      $plan = '24hrs'; $duration = 86400;
      if ($amount == 10) { $plan='2hrs'; $duration=7200; }
      if ($amount == 30) { $plan='6hrs'; $duration=21600; }
      if ($amount == 50) { $plan='12hrs'; $duration=43200; }
      if ($amount == 80) { $plan='24hrs'; $duration=86400; }
      if ($amount == 300) { $plan='1week'; $duration=604800; }

      $expires_at = date('Y-m-d H:i:s', time() + $duration);

      $stmt = $pdo->prepare('INSERT INTO vouchers (code, plan, duration_seconds, created_by_payment_id, expires_at) VALUES (?,?,?,?,?)');
      $stmt->execute([$code, $plan, $duration, $payment['id'], $expires_at]);

      // Try to push to router
      $pushed = create_hotspot_user($code, $plan);

      http_response_code(200);
      echo json_encode(['status'=>'success','voucher'=>$code,'router_pushed'=>$pushed ? true : false]);
      exit;
    } else {
      http_response_code(200);
      echo json_encode(['status'=>'pending']);
      exit;
    }
} catch (Exception $e) {
    error_log('confirm.php error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','msg'=>'Server error: ' . $e->getMessage()]);
}
