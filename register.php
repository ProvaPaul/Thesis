<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect_for_role(current_user());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'annotator']);

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            header('Location: annotation.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'This email is already registered.';
            } else {
                throw $exception;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Annotator Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
  <main class="auth-card shadow-sm">
    <h1 class="h4 mb-1">Create Account</h1>
    <p class="text-muted mb-4">Sign up as an annotator.</p>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="vstack gap-3">
      <div>
        <label class="form-label" for="name">Name</label>
        <input class="form-control" id="name" name="name" required autofocus>
      </div>
      <div>
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" required>
      </div>
      <div>
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" minlength="6" required>
      </div>
      <div>
        <label class="form-label" for="confirm_password">Confirm Password</label>
        <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="6" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Sign Up</button>
    </form>
    <p class="small text-muted mt-4 mb-0">Already have an account? <a href="login.php">Login</a></p>
  </main>
</body>
</html>
