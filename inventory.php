<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}

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

$pdo = get_admin_pdo();
$products = $pdo->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();

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
    <title>Inventory - NEW CREATION FOOD INC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body{ background:#f4f4f5; }
      .brand-badge{ display:flex; align-items:center; gap:.5rem; font-weight:800; text-transform:uppercase; }
      .brand-badge img{ width:44px; height:44px; object-fit:contain; }
      .table-card{ background:#fff; border-radius:10px; padding:16px; }
      .price{ font-weight:700; }
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
            <a class="btn btn-secondary btn-sm" href="admin_dashboard.php">Back to dashboard</a>
            <a class="btn btn-primary btn-sm" href="product_new.php">New product</a>
            <a class="btn btn-outline-dark btn-sm" href="movement_new.php">Record stock</a>
          </div>
        </div>
      </nav>

      <div class="table-card">
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Name</th>
                <th class="text-end">Unit cost</th>
                <th class="text-end">Markup %</th>
                <th class="text-end">Tax %</th>
                <th class="text-end">Price</th>
                <th class="text-end">Stock</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): $calc = compute_price((float)$p['unit_cost'], (float)$p['markup_pct'], (float)$p['tax_pct']); ?>
              <tr>
                <td><?php echo htmlspecialchars($p['sku']); ?></td>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td class="text-end">₱<?php echo number_format((float)$p['unit_cost'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$p['markup_pct'], 2); ?>%</td>
                <td class="text-end"><?php echo number_format((float)$p['tax_pct'], 2); ?>%</td>
                <td class="text-end price">₱<?php echo number_format($calc['finalPrice'], 2); ?></td>
                <td class="text-end"><?php echo number_format(get_stock($pdo, (int)$p['id']), 3); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </body>
  </html>


