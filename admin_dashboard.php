<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}

$pdo = get_admin_pdo();

// Metrics
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalClients = 0; // Not applicable in separate database system
$stockOnHand = (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN movement_type='in' THEN quantity ELSE -quantity END),0) FROM inventory_movements")->fetchColumn();
// Movement stats
$totalIn = (float)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type='in'")->fetchColumn();
$totalOut = (float)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type='out'")->fetchColumn();
// Low stock count (< 10 units)
$lowStockStmt = $pdo->query("SELECT COUNT(*) FROM (
  SELECT p.id, COALESCE(SUM(CASE WHEN m.movement_type='in' THEN m.quantity ELSE -m.quantity END),0) AS stock
  FROM products p LEFT JOIN inventory_movements m ON m.product_id=p.id
  GROUP BY p.id
  HAVING stock < 10
) s");
$lowStockCount = (int)$lowStockStmt->fetchColumn();

// Order statistics
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$todayOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('confirmed','preparing','shipped','delivered')")->fetchColumn();

function compute_price(float $unitCost, float $markupPct, float $taxPct): float {
  $preTax = $unitCost + ($unitCost * ($markupPct / 100.0));
  $tax = $preTax * ($taxPct / 100.0);
  return round($preTax + $tax, 2);
}

$recent = $pdo->query('SELECT id, sku, name, unit_cost, markup_pct, tax_pct FROM products ORDER BY id DESC LIMIT 5')->fetchAll();
// Average selling price across products
$priceAccumulator = 0.0; $priceN = 0;
foreach ($recent as $tmp) { $priceAccumulator += compute_price((float)$tmp['unit_cost'], (float)$tmp['markup_pct'], (float)$tmp['tax_pct']); $priceN++; }
$avgRecentPrice = $priceN ? round($priceAccumulator / $priceN, 2) : 0.00;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Dashboard</h3>
        <div class="d-flex gap-2">
          <a class="btn btn-primary btn-sm" href="admin_orders.php">Manage Orders</a>
          <a class="btn btn-outline-primary btn-sm" href="admin_payments.php">Payments</a>
          <a class="btn btn-secondary btn-sm" href="products.php?preview=1">Shop view</a>
          <a class="btn btn-outline-dark btn-sm" href="admin_logout.php">Logout</a>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Products</div>
              <div class="display-6"><?php echo number_format($totalProducts); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Clients</div>
              <div class="display-6"><?php echo number_format($totalClients); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Orders</div>
              <div class="display-6"><?php echo number_format($totalOrders); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Pending Orders</div>
              <div class="display-6 text-warning"><?php echo number_format($pendingOrders); ?></div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Today's Orders</div>
              <div class="display-6 text-info"><?php echo number_format($todayOrders); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Revenue</div>
              <div class="display-6 text-success">₱<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Stock On Hand</div>
              <div class="display-6"><?php echo number_format($stockOnHand, 3); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Low Stock Items</div>
              <div class="display-6 text-danger"><?php echo number_format($lowStockCount); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Stock In</div>
              <div class="h3 mb-0"><?php echo number_format($totalIn, 3); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Total Stock Out</div>
              <div class="h3 mb-0"><?php echo number_format($totalOut, 3); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted">Low Stock Products (&lt;10)</div>
              <div class="h3 mb-0"><?php echo number_format($lowStockCount); ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="inventory.php" class="btn btn-primary">Inventory</a>
        <a href="product_new.php" class="btn btn-success">Add Product</a>
        <a href="movement_new.php" class="btn btn-outline-primary">Record Stock</a>
        <a href="admin_product_edit.php" class="btn btn-warning">Edit Prices & Stock</a>
      </div>

      <div class="card">
        <div class="card-header">Recent Products</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0 align-middle">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Name</th>
                  <th class="text-end">Price</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $p): $price = compute_price((float)$p['unit_cost'], (float)$p['markup_pct'], (float)$p['tax_pct']); ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['sku']); ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td class="text-end">₱<?php echo number_format($price, 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="p-2 text-end text-muted small">Avg price of recent items: ₱<?php echo number_format($avgRecentPrice, 2); ?></div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>


