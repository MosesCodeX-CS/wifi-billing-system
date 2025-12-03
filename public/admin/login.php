<?php
require_once __DIR__ . '/../../core/config.php';
session_start();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $err = 'Invalid credentials';
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin Login</title></head><body>
<h2>Admin Login</h2>
<?php if($err) echo '<p style="color:red">'.htmlspecialchars($err).'</p>'; ?>
<form method="post">
<label>Username<input name="username"></label><br>
<label>Password<input type="password" name="password"></label><br>
<button type="submit">Login</button>
</form>
</body></html>
