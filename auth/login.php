<?php
require_once __DIR__ . '/../config/app.php';
if (isLoggedIn()) redirect(BASE_URL . '/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;
            auditLog('login', 'users', $user['id'], 'User logged in');
            redirect(BASE_URL . '/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Fraunces:wght@300;600&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center mb-4">
      <div class="login-logo"><i class="bi bi-book-half me-2"></i><?= APP_NAME ?></div>
      <div class="login-sub">Library Management System</div>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger d-flex gap-2 align-items-center py-2">
        <i class="bi bi-exclamation-triangle-fill"></i><?= sanitize($error) ?>
      </div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
          <input type="text" name="username" class="form-control border-start-0 ps-0"
                 placeholder="Enter username" value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
          <input type="password" name="password" class="form-control border-start-0 ps-0"
                 placeholder="Enter password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>
    <p class="text-center text-muted mt-4 mb-0" style="font-size:.75rem">
      Default: <code>admin</code> / <code>Pass123,</code>
    </p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
