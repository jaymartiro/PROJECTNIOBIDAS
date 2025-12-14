<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();

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
      $pdo = get_user_pdo();
      $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $errors[] = 'Email already registered.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
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
    <title>Sign up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
    <?php
    // Reuse the image helper like login.php
    $asset = function(string $filename, string $fallbackUrl): string {
      $imagesPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
      if (file_exists($imagesPath)) { return 'images/' . $filename; }
      $assetsPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $filename;
      if (file_exists($assetsPath)) { return 'assets/' . $filename; }
      return $fallbackUrl;
    };
    $logoUrl = $asset('Logo NCF.jpg', 'https://i.imgur.com/3vQn3sF.png');
    $cardBgUrl = $asset('Longanisa Hamonada.jpg', $asset('BG.jpg', 'https://images.unsplash.com/photo-1544025162-d76694265947?q=80&w=2070&auto=format&fit=crop'));
    ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body { background:#e5e7eb; }
      .brand { display:flex; align-items:center; gap:16px; justify-content:center; margin:26px 0 6px; }
      .brand img { width:96px; height:96px; object-fit:contain; }
      .brand-title { text-align:center; font-weight:800; font-size:34px; letter-spacing:.5px; line-height:1.05; }
      .brand-title div:first-child { margin-top:2px; }
      .panel { max-width:720px; margin:0 auto; }
      .signup-frame { position:relative; border-radius:10px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,.18); border:2px solid rgba(0,0,0,.2); }
      .signup-frame::before { content:""; position:absolute; inset:0; background:url('<?php echo htmlspecialchars($cardBgUrl, ENT_QUOTES); ?>') center/cover no-repeat; opacity:.9; filter:brightness(.9); }
      .signup-inner { position:relative; z-index:1; padding:18px; }
      .card { background:rgba(255,255,255,.92); border:0; }
      h3 { letter-spacing:1px; font-weight:800; }
      .form-control { height:54px; border-radius:10px; font-size:18px; }
      .btn-primary { background:#e11d29; border-color:#e11d29; height:58px; font-size:24px; font-weight:700; border-radius:10px; }
      .btn-primary:hover { background:#c31621; border-color:#c31621; }
    </style>
    <div class="container py-4 py-md-5">
      <div class="brand">
        <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="Logo" onerror="this.style.display='none'">
        <div class="brand-title">
          <div>NEW CREATION</div>
          <div>FOOD INC</div>
        </div>
      </div>
      <div class="row justify-content-center">
        <div class="col-12 panel">
          <div class="signup-frame rounded-3">
            <div class="signup-inner">
              <div class="card shadow-sm">
                <div class="card-body p-4">
                  <h3 class="mb-3 text-center">Create account</h3>
              <?php if ($success): ?>
                <div class="alert alert-success">Account created. You can now <a href="login.php">log in</a>.</div>
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
                <button class="btn btn-primary w-100" type="submit">Sign up</button>
              </form>
              <div class="text-center mt-3"><a href="login.php">Already have an account? Log in</a></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  </html>


