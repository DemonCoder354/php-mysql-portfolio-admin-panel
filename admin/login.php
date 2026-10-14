<?php
require __DIR__ . '/../includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Simple brute-force protection: 5 attempts, then a 5-minute lockout
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'since' => time()];
    if (time() - $attempts['since'] > 300) {
        $attempts = ['count' => 0, 'since' => time()];
    }

    if ($attempts['count'] >= 5) {
        $error = 'Too many attempts. Please wait 5 minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
        $admin = $stmt->fetch();

        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            unset($_SESSION['login_attempts']);
            redirect('index.php');
        }
        $attempts['count']++;
        $error = 'Invalid username or password.';
    }
    $_SESSION['login_attempts'] = $attempts;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login">
  <form class="card form login-card" method="post">
    <h1>Sign in</h1>
    <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--primary" type="submit">Sign in</button>
    <a href="../index.php" class="muted">← Back to site</a>
  </form>
</body>
</html>
