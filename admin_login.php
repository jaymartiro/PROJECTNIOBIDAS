<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required.'; }
  if ($password === '') { $errors[] = 'Password is required.'; }

  if (!$errors) {
    try {
      $pdo = get_admin_pdo();
      $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_super_admin FROM admin_users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $user = $stmt->fetch();
      if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
      } else {
        // Admin login - no need to clear user sessions as they're in separate databases
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['is_super_admin'] = (int)$user['is_super_admin'];
        header('Location: admin_dashboard.php');
        exit;
      }
    } catch (Throwable $e) {
      $errors[] = 'Database error: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Log in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <h3 class="mb-3">Admin Log in</h3>
              <?php if ($errors): ?>
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                      <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <form method="post" novalidate>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="mb-4">
                  <label class="form-label">Password</label>
                  <input type="password" class="form-control" name="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Log in</button>
              </form>
              <div class="text-center mt-3"><a href="admin_signup.php">Create admin account</a></div>
              <div class="text-center mt-1"><a href="login.php">Customer login</a></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  </html>


