<?php
require_once __DIR__ . '/admin_config.php';
$pdo = get_admin_pdo();

function get_stock(PDO $pdo, int $productId): float {
  $in = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) q FROM inventory_movements WHERE product_id=? AND movement_type='in'");
  $in->execute([$productId]);
  $qin = (float)$in->fetchColumn();
  $out = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) q FROM inventory_movements WHERE product_id=? AND movement_type='out'");
  $out->execute([$productId]);
  $qout = (float)$out->fetchColumn();
  return $qin - $qout;
}

function compute_price(float $unitCost, float $markupPct, float $taxPct): array {
  $markupAmount = $unitCost * ($markupPct / 100.0);
  $preTax = $unitCost + $markupAmount;
  $taxAmount = $preTax * ($taxPct / 100.0);
  $finalPrice = $preTax + $taxAmount;
  return [
    'preTax' => round($preTax, 2),
    'taxAmount' => round($taxAmount, 2),
    'finalPrice' => round($finalPrice, 2),
  ];
}

$products = $pdo->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected = null;
foreach ($products as $p) { if ((int)$p['id'] === $selectedId) { $selected = $p; break; } }

// branding assets
$asset = function(string $filename, string $fallbackUrl): string {
  $imagesPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
  if (file_exists($imagesPath)) { return 'images/' . $filename; }
  return $fallbackUrl;
};
$logoUrl = $asset('Logo NCF.jpg', 'https://i.imgur.com/3vQn3sF.png');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Product - NEW CREATION FOOD INC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body{ background:#f4f4f5; }
      .brand-badge{ display:flex; align-items:center; gap:.5rem; font-weight:800; text-transform:uppercase; }
      .brand-badge img{ width:44px; height:44px; object-fit:contain; }
      .card-img-top{ height:260px; object-fit:cover; }
    </style>
  </head>
  <body>
    <div class="container py-3 py-md-4">
      <nav class="navbar navbar-expand-lg bg-white rounded-3 px-3 py-2 mb-3">
        <div class="container-fluid px-0">
          <div class="brand-badge">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="Logo">
            <div>
              <div style="font-size:14px; color:#6b7280; line-height:1">NEW CREATION</div>
              <div style="font-size:16px; color:#111827; line-height:1">FOOD INC</div>
            </div>
          </div>
          <div class="d-flex gap-2 ms-auto">
            <a class="btn btn-secondary btn-sm" href="products.php">Back</a>
            <a class="btn btn-primary btn-sm" href="inventory.php">Inventory</a>
          </div>
        </div>
      </nav>

      <div class="bg-white rounded-3 p-3 p-md-4 mb-3">
        <form class="row g-2 align-items-end" method="get">
          <div class="col-12 col-md-8">
            <label class="form-label">Choose product</label>
            <select class="form-select" name="id" required>
              <option value="">-- select --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo ($selectedId === (int)$p['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($p['sku'] . ' — ' . $p['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <button class="btn btn-dark w-100" type="submit">View</button>
          </div>
        </form>
      </div>

      <?php if ($selected): $calc = compute_price((float)$selected['unit_cost'], (float)$selected['markup_pct'], (float)$selected['tax_pct']); ?>
        <div class="row">
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm">
              <?php if (!empty($selected['image_path'])): ?>
                <img class="card-img-top" src="<?php echo htmlspecialchars($selected['image_path'], ENT_QUOTES); ?>" alt="Image">
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title mb-1"><?php echo htmlspecialchars($selected['name']); ?></h5>
                <div class="text-muted mb-2">SKU: <?php echo htmlspecialchars($selected['sku']); ?></div>
                <div class="d-flex justify-content-between"><span>Unit cost</span><strong>₱<?php echo number_format((float)$selected['unit_cost'], 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span>Markup</span><strong><?php echo number_format((float)$selected['markup_pct'], 2); ?>%</strong></div>
                <div class="d-flex justify-content-between"><span>Tax</span><strong><?php echo number_format((float)$selected['tax_pct'], 2); ?>%</strong></div>
                <hr>
                <div class="d-flex justify-content-between"><span>Pre-tax price</span><strong>₱<?php echo number_format($calc['preTax'], 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span>Tax amount</span><strong>₱<?php echo number_format($calc['taxAmount'], 2); ?></strong></div>
                <div class="d-flex justify-content-between fs-5"><span>Final price</span><strong>₱<?php echo number_format($calc['finalPrice'], 2); ?></strong></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm">
              <div class="card-body">
                <h5 class="card-title mb-3">Stock</h5>
                <div class="display-6"><?php echo number_format(get_stock($pdo, (int)$selected['id']), 3); ?></div>
                <div class="text-muted">Current on hand</div>
                <div class="mt-4 d-flex gap-2">
                  <a href="movement_new.php" class="btn btn-outline-dark">Record movement</a>
                  <a href="inventory.php" class="btn btn-primary">Go to inventory</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </body>
  </html>


