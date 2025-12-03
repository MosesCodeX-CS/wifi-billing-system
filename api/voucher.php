<?php
// api/voucher.php - user pastes voucher code; server activates by creating hotspot user on router
try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/mikrotik.php';

    header('Content-Type: application/json; charset=utf-8');

    $voucher = trim($_POST['voucher'] ?? '');
    if (!$voucher) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'empty voucher']);
      exit;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE code = ?');
    $stmt->execute([$voucher]);
    $v = $stmt->fetch();

    if (!$v) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'invalid voucher']);
      exit;
    }

    if ($v['used']) {
      http_response_code(400);
      echo json_encode(['status'=>'error','msg'=>'voucher already used']);
      exit;
    }

    // Try to create hotspot user
    $ok = create_hotspot_user($voucher, $v['plan']);
    if ($ok) {
      // mark used
      $stmt = $pdo->prepare('UPDATE vouchers SET used = 1 WHERE id = ?');
      $stmt->execute([$v['id']]);
      http_response_code(200);
      echo json_encode(['status'=>'ok','username'=>$voucher]);
    } else {
      http_response_code(500);
      echo json_encode(['status'=>'error','msg'=>'router error - voucher stored but not activated']);
    }
} catch (Exception $e) {
    error_log('voucher.php error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','msg'=>'Server error: ' . $e->getMessage()]);
}
