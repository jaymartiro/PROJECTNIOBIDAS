<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}
$pdo = get_admin_pdo();

$products = $pdo->query('SELECT id, sku, name FROM products ORDER BY name ASC')->fetchAll();
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = (int)($_POST['product_id'] ?? 0);
  $movement_type = $_POST['movement_type'] ?? 'in';
  $quantity = (float)($_POST['quantity'] ?? 0);
  $note = trim($_POST['note'] ?? '');

  if ($product_id <= 0) { $errors[] = 'Product is required.'; }
  if ($quantity <= 0) { $errors[] = 'Quantity must be greater than 0.'; }
  if ($movement_type !== 'in' && $movement_type !== 'out') { $errors[] = 'Invalid movement type.'; }

  if (!$errors) {
    try {
      $stmt = $pdo->prepare('INSERT INTO inventory_movements (product_id, movement_type, quantity, note) VALUES (?, ?, ?, ?)');
      $stmt->execute([$product_id, $movement_type, $quantity, $note]);
      $saved = true;
    } catch (Throwable $e) {
      $errors[] = 'Unable to save: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record Stock Movement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Record Stock</h3>
        <div class="d-flex gap-2">
          <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Back to dashboard</a>
          <a href="inventory.php" class="btn btn-outline-dark btn-sm">Back to inventory</a>
        </div>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success">Saved!</div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Product</label>
          <select name="product_id" class="form-select" required>
            <option value="">-- choose --</option>
            <?php foreach ($products as $p): ?>
              <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)($_POST['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($p['sku'] . ' — ' . $p['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Type</label>
          <select name="movement_type" class="form-select" required>
            <option value="in" <?php echo (($_POST['movement_type'] ?? '') === 'in') ? 'selected' : ''; ?>>Stock In</option>
            <option value="out" <?php echo (($_POST['movement_type'] ?? '') === 'out') ? 'selected' : ''; ?>>Stock Out</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Quantity</label>
          <input type="number" step="0.001" name="quantity" class="form-control" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-control" value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>">
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">Save movement</button>
        </div>
      </form>
    </div>
  </body>
  </html>


