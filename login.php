<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect_for_role(current_user());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        redirect_for_role($user);
    }

    $error = 'Invalid email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bangla WSD Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
  <main class="auth-card shadow-sm">
    <h1 class="h4 mb-1">Bangla WSD</h1>
    <p class="text-muted mb-4">Sign in to continue annotation work.</p>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="vstack gap-3">
      <div>
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" required autofocus>
      </div>
      <div>
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Login</button>
    </form>
    <p class="small text-muted mt-4 mb-1">Default demo password: <strong>password</strong></p>
    <p class="small text-muted mb-0">New annotator? <a href="register.php">Create an account</a></p>
  </main>
</body>
</html>
