<?php
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/db.php';
$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM vouchers WHERE created_by_payment_id = ?');
$stmt->execute([6]);
$v = $stmt->fetchAll();
print_r($v);
?>