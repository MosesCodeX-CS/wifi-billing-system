<?php
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/mpesa.php';
$json = '{"Body":{"stkCallback":{"MerchantRequestID":"0121-TEST","CheckoutRequestID":"ws_CO_03122025130643595742784172","ResultCode":0,"ResultDesc":"The service request is processed successfully.","CallbackMetadata":{"Item":[{"Name":"Amount","Value":10.00},{"Name":"MpesaReceiptNumber","Value":"ABC12345"},{"Name":"TransactionDate","Value":20251203130645},{"Name":"PhoneNumber","Value":"254742784172"}]}}}}';
$data = json_decode($json, true);
$r = mpesa_record_callback($data);
var_export($r);
echo PHP_EOL;
?>