<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/db.php';
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php'); exit;
}
$pdo = get_db();
$payments = $pdo->query('SELECT * FROM payments ORDER BY created_at DESC LIMIT 100')->fetchAll();
$vouchers = $pdo->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin Dashboard</title></head><body>
<h2>Admin Dashboard</h2>
<p><a href="logout.php">Logout</a></p>
<h3>Recent Payments</h3>
<table border="1" cellpadding="6"><tr><th>ID</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th></tr>
<?php foreach($payments as $p): ?>
<tr>
<td><?=htmlspecialchars($p['id'])?></td>
<td><?=htmlspecialchars($p['phone'])?></td>
<td><?=htmlspecialchars($p['amount'])?></td>
<td><?=htmlspecialchars($p['status'])?></td>
<td><?=htmlspecialchars($p['created_at'])?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Vouchers</h3>
<table border="1" cellpadding="6"><tr><th>ID</th><th>Code</th><th>Plan</th><th>Used</th><th>Expires</th></tr>
<?php foreach($vouchers as $v): ?>
<tr>
<td><?=htmlspecialchars($v['id'])?></td>
<td><?=htmlspecialchars($v['code'])?></td>
<td><?=htmlspecialchars($v['plan'])?></td>
<td><?=htmlspecialchars($v['used'])?></td>
<td><?=htmlspecialchars($v['expires_at'])?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
