<?php
// core/mpesa.php - Daraja STK push + helpers with environment switch and demo mode
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
// optional router push helper
require_once __DIR__ . '/mikrotik.php';

// Check if curl extension is available
$curl_available = function_exists('curl_init');

function mpesa_base_url() {
    if (function_exists('getMpesaBaseUrl')) return getMpesaBaseUrl();
    return (defined('MPESA_ENV') && MPESA_ENV === 'production')
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

// Helper to evaluate demo mode consistently even when loaded from .env as string
function mpesa_is_demo_mode()
{
    if (defined('MPESA_DEMO_MODE')) {
        $v = MPESA_DEMO_MODE;
        if (is_string($v)) {
            return in_array(strtolower($v), ['1','true','yes'], true);
        }
        return (bool)$v;
    }
    return false;
}

function mpesa_get_access_token() {
    global $curl_available;
    
    // In demo mode or if curl unavailable, return a mock token
    if (!$curl_available || mpesa_is_demo_mode()) {
        return 'demo_token_' . time();
    }
    
    try {
        $credentials = MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET;
        $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
        $ch = curl_init($url);
        if (!$ch) {
            throw new Exception('curl_init failed');
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($credentials)]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            throw new Exception('curl error: ' . $err);
        }
        
        $j = json_decode($resp, true);
        if (!isset($j['access_token'])) {
            throw new Exception('No access token in response: ' . substr($resp, 0, 200));
        }
        return $j['access_token'];
    } catch (Exception $e) {
        error_log('MPESA token error: ' . $e->getMessage());
        return null;
    }
}

function mpesa_stk_push($phone, $amount, $accountRef = 'BellamyHotspot') {
    global $curl_available;
    
    // Demo mode: simulate successful STK push
    if (mpesa_is_demo_mode()) {
        return [
            'ResponseCode' => '0',
            'CheckoutRequestID' => 'demo_' . time() . '_' . rand(1000, 9999),
            'MerchantRequestID' => 'demo_' . rand(1000, 9999),
            'ResponseDescription' => 'Success. Request accepted for processing.'
        ];
    }
    
    // If curl unavailable, return error
    if (!$curl_available) {
        error_log('MPESA STK: cURL not available');
        return [
            'ResponseCode' => '1',
            'ResponseDescription' => 'Server error: cURL extension not installed. Contact administrator.',
            'error' => 'curl_not_available'
        ];
    }
    
    try {
        $token = mpesa_get_access_token();
        if (!$token) {
            return [
                'ResponseCode' => '1',
                'ResponseDescription' => 'Failed to get access token',
                'error' => 'token_failed'
            ];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int)$amount,
            'PartyA' => $phone,
            'PartyB' => MPESA_SHORTCODE,
            'PhoneNumber' => $phone,
            'CallBackURL' => defined('MPESA_CALLBACK_URL') ? MPESA_CALLBACK_URL : MPESA_CALLBACK,
            'AccountReference' => $accountRef,
            'TransactionDesc' => 'Hotspot payment'
        ];

        $url = mpesa_base_url() . '/mpesa/stkpush/v1/processrequest';
        $ch = curl_init($url);
        if (!$ch) {
            throw new Exception('curl_init failed for STK push');
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json","Authorization: Bearer $token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            throw new Exception('curl error during STK push: ' . $err);
        }
        
        $j = json_decode($resp, true);
        if (!$j) {
            throw new Exception('Invalid JSON response: ' . substr($resp, 0, 200));
        }
        return $j;
    } catch (Exception $e) {
        error_log('MPESA STK push error: ' . $e->getMessage());
        return [
            'ResponseCode' => '1',
            'ResponseDescription' => 'Error: ' . $e->getMessage(),
            'error' => 'exception'
        ];
    }
}

// Reconcile pending payments by querying the payments table and attempting a status check if necessary.
// NOTE: In production you should call the appropriate M-Pesa transaction status endpoints.
// This function demonstrates marking old pending payments as failed after timeout.
function mpesa_reconcile_pending() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM payments WHERE status='PENDING' AND created_at < (NOW() - INTERVAL 10 MINUTE)");
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        // For demo: mark as FAILED (or implement actual query to M-Pesa)
        $u = $pdo->prepare('UPDATE payments SET status=?, result_desc=? WHERE id=?');
        $u->execute(['FAILED','Timed out - no callback', $r['id']]);
    }
    return true;
}

function mpesa_record_callback($data) {
    $pdo = get_db();
    if (!isset($data['Body']['stkCallback'])) return false;
    $cb = $data['Body']['stkCallback'];
    $checkout = $cb['CheckoutRequestID'] ?? null;
    $merchantRequestID = $cb['MerchantRequestID'] ?? null;
    $resultCode = $cb['ResultCode'] ?? null;
    $resultDesc = $cb['ResultDesc'] ?? null;

    $amount = null; $phone = null; $receipt = null;
    if (isset($cb['CallbackMetadata']['Item']) && is_array($cb['CallbackMetadata']['Item'])) {
        foreach ($cb['CallbackMetadata']['Item'] as $it) {
            if (($it['Name'] ?? '') === 'Amount') $amount = $it['Value'];
            if (($it['Name'] ?? '') === 'MpesaReceiptNumber') $receipt = $it['Value'];
            if (($it['Name'] ?? '') === 'PhoneNumber') $phone = $it['Value'];
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM payments WHERE checkout_request_id = ?');
    $stmt->execute([$checkout]);
    $existing = $stmt->fetch();
    $status = ($resultCode === 0) ? 'SUCCESS' : 'FAILED';

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE payments SET merchant_request_id=?, result_code=?, result_desc=?, receipt_number=?, phone=?, amount=?, status=?, raw_callback=? WHERE checkout_request_id=?');
        $stmt->execute([$merchantRequestID, $resultCode, $resultDesc, $receipt, $phone, $amount, $status, json_encode($data), $checkout]);
        $payment_id = $existing['id'];
        // If successful payment, attempt to create voucher immediately
        if ($status === 'SUCCESS') {
            try {
                mpesa_create_voucher_for_payment($payment_id);
            } catch (Exception $e) {
                error_log('mpesa_record_callback: voucher creation error: ' . $e->getMessage());
            }
        }
        return true;
    } else {
        $stmt = $pdo->prepare('INSERT INTO payments (checkout_request_id, merchant_request_id, phone, amount, status, receipt_number, result_code, result_desc, raw_callback) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$checkout, $merchantRequestID, $phone, $amount, $status, $receipt, $resultCode, $resultDesc, json_encode($data)]);
        $payment_id = $pdo->lastInsertId();
        if ($status === 'SUCCESS') {
            try {
                mpesa_create_voucher_for_payment($payment_id);
            } catch (Exception $e) {
                error_log('mpesa_record_callback: voucher creation error: ' . $e->getMessage());
            }
        }
        return true;
    }
}

/**
 * Create a voucher for a successful payment and attempt to push to the router.
 * This mirrors the logic in `api/confirm.php` so vouchers are created automatically
 * when a SUCCESS callback arrives.
 */
function mpesa_create_voucher_for_payment($payment_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    if (!$payment) {
        throw new Exception('Payment not found: ' . $payment_id);
    }

    // Check if voucher already exists for this payment
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE created_by_payment_id = ?');
    $stmt->execute([$payment_id]);
    $v = $stmt->fetch();
    if ($v) return $v['code'];

    // Generate voucher code and plan mapping
    $code = 'BELL' . strval(rand(10000, 99999));
    $amount = floatval($payment['amount']);
    $plan = '24hrs'; $duration = 86400;
    if ($amount == 10) { $plan='2hrs'; $duration=7200; }
    if ($amount == 30) { $plan='6hrs'; $duration=21600; }
    if ($amount == 50) { $plan='12hrs'; $duration=43200; }
    if ($amount == 80) { $plan='24hrs'; $duration=86400; }
    if ($amount == 300) { $plan='1week'; $duration=604800; }

    $expires_at = date('Y-m-d H:i:s', time() + $duration);

    $stmt = $pdo->prepare('INSERT INTO vouchers (code, plan, duration_seconds, created_by_payment_id, expires_at) VALUES (?,?,?,?,?)');
    $stmt->execute([$code, $plan, $duration, $payment_id, $expires_at]);

    // Attempt to push to hotspot router; create_hotspot_user() may return true/false
    $pushed = false;
    try {
        $pushed = create_hotspot_user($code, $plan);
        if ($pushed) {
            // mark voucher used (activated) if router applied it immediately
            $stmt = $pdo->prepare('UPDATE vouchers SET used = 1 WHERE code = ?');
            $stmt->execute([$code]);
        }
    } catch (Exception $e) {
        error_log('mpesa_create_voucher_for_payment: router push failed: ' . $e->getMessage());
    }

    // Optionally, update payments table to link voucher code
    try {
        $stmt = $pdo->prepare("UPDATE payments SET receipt_number=?, result_desc=CONCAT(IFNULL(result_desc,''), ?), raw_callback=raw_callback WHERE id=?");
        // store voucher info in result_desc for audit (non-destructive)
        $stmt->execute([$payment['receipt_number'] ?? null, " | voucher:$code", $payment_id]);
    } catch (Exception $e) {
        // ignore non-critical
    }

    return $code;
}
