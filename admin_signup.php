<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm'] ?? '';

  if ($name === '') { $errors[] = 'Name is required.'; }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required.'; }
  if (strlen($password) < 6) { $errors[] = 'Password must be at least 6 characters.'; }
  if ($password !== $confirm) { $errors[] = 'Passwords do not match.'; }

  if (!$errors) {
    try {
      $pdo = get_admin_pdo();
      $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $errors[] = 'Email already registered.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, is_super_admin) VALUES (?, ?, ?, 0)');
        $insert->execute([$name, $email, $hash]);
        $success = true;
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
    <title>Create Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <h3 class="mb-3">Create admin account</h3>
              <?php if ($success): ?>
                <div class="alert alert-success">Admin created. You can now <a href="admin_login.php">log in</a>.</div>
              <?php endif; ?>
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
                  <label class="form-label">Name</label>
                  <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Password</label>
                  <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-4">
                  <label class="form-label">Confirm Password</label>
                  <input type="password" class="form-control" name="confirm" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Create admin</button>
              </form>
              <div class="text-center mt-3"><a href="admin_login.php">Back to admin login</a></div>
              <div class="text-center mt-1"><a href="client_signup.php">Client signup</a></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  </html>


