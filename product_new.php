<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}
$pdo = get_admin_pdo();

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sku = trim($_POST['sku'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $unit_cost = (float)($_POST['unit_cost'] ?? 0);
  $markup_pct = (float)($_POST['markup_pct'] ?? 0);
  $tax_pct = (float)($_POST['tax_pct'] ?? 0);
  $image_path = trim($_POST['image_path'] ?? '');
  $initial_stock = (float)($_POST['initial_stock'] ?? 0);

  // Handle file upload if provided
  if (!empty($_FILES['image_file']['name'])) {
    $upload = $_FILES['image_file'];
    if ($upload['error'] === UPLOAD_ERR_OK) {
      $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/jpg' => 'jpg'];
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $upload['tmp_name']);
      finfo_close($finfo);
      if (!isset($allowed[$mime])) {
        $errors[] = 'Image must be JPG, PNG, or WEBP.';
      } else {
        $ext = $allowed[$mime];
        $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($upload['name'], PATHINFO_FILENAME));
        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'images';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        $targetRel = 'images/' . $base . '_' . uniqid() . '.' . $ext;
        $targetAbs = __DIR__ . DIRECTORY_SEPARATOR . $targetRel;
        if (!move_uploaded_file($upload['tmp_name'], $targetAbs)) {
          $errors[] = 'Failed to save uploaded image.';
        } else {
          $image_path = $targetRel;
        }
      }
    } elseif ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Upload error code: ' . (int)$upload['error'];
    }
  }

  if ($sku === '' || $name === '') { $errors[] = 'SKU and name are required.'; }
  if ($unit_cost < 0) { $errors[] = 'Unit cost must be 0 or more.'; }
  if ($initial_stock < 0) { $errors[] = 'Initial stock cannot be negative.'; }
  if (!$errors) {
    try {
      $stmt = $pdo->prepare('INSERT INTO products (sku, name, image_path, unit_cost, markup_pct, tax_pct) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([$sku, $name, $image_path, $unit_cost, $markup_pct, $tax_pct]);
      $productId = (int)$pdo->lastInsertId();
      if ($initial_stock > 0) {
        $m = $pdo->prepare('INSERT INTO inventory_movements (product_id, movement_type, quantity, note) VALUES (?, "in", ?, ?)');
        $m->execute([$productId, $initial_stock, 'Initial stock']);
      }
      $saved = true;
    } catch (Throwable $e) {
      $errors[] = 'Unable to save: ' . $e->getMessage();
    }
  }
}

function compute_price(float $unitCost, float $markupPct, float $taxPct): array {
  $markupAmount = $unitCost * ($markupPct / 100.0);
  $preTax = $unitCost + $markupAmount;
  $taxAmount = $preTax * ($taxPct / 100.0);
  $finalPrice = $preTax + $taxAmount;
  return [round($preTax,2), round($taxAmount,2), round($finalPrice,2)];
}

list($preTax, $taxAmount, $finalPrice) = compute_price((float)($_POST['unit_cost'] ?? 0), (float)($_POST['markup_pct'] ?? 0), (float)($_POST['tax_pct'] ?? 0));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">New Product</h3>
        <div class="d-flex gap-2">
          <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Back to dashboard</a>
          <a href="inventory.php" class="btn btn-outline-dark btn-sm">Back to inventory</a>
        </div>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success">Saved! <a href="inventory.php">Go to inventory</a></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3" enctype="multipart/form-data">
        <div class="col-md-4">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" required>
        </div>
        <div class="col-md-8">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Unit cost (₱)</label>
          <input type="number" step="0.01" name="unit_cost" class="form-control" value="<?php echo htmlspecialchars($_POST['unit_cost'] ?? '0'); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Markup %</label>
          <input type="number" step="0.01" name="markup_pct" class="form-control" value="<?php echo htmlspecialchars($_POST['markup_pct'] ?? '0'); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tax %</label>
          <input type="number" step="0.01" name="tax_pct" class="form-control" value="<?php echo htmlspecialchars($_POST['tax_pct'] ?? '0'); ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Product image (optional)</label>
          <input type="file" name="image_file" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <div class="form-text">You can also paste a path below instead of uploading.</div>
          <input type="text" name="image_path" class="form-control mt-2" placeholder="images/yourimage.jpg" value="<?php echo htmlspecialchars($_POST['image_path'] ?? ''); ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Initial stock (optional)</label>
          <input type="number" step="0.001" name="initial_stock" class="form-control" value="<?php echo htmlspecialchars($_POST['initial_stock'] ?? '0'); ?>">
          <div class="form-text">On save, this quantity will be recorded as Stock In.</div>
        </div>

        <div class="col-12">
          <div class="alert alert-secondary mb-0">
            <div>Computed pre-tax price: <strong>₱<?php echo number_format($preTax,2); ?></strong></div>
            <div>Tax amount: <strong>₱<?php echo number_format($taxAmount,2); ?></strong></div>
            <div>Final price (incl. tax): <strong>₱<?php echo number_format($finalPrice,2); ?></strong></div>
          </div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit">Save product</button>
        </div>
      </form>
    </div>
  </body>
  </html>


