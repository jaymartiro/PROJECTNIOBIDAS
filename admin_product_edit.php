<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}

$pdo = get_admin_pdo();

// Handle product updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  $productId = (int)($_POST['product_id'] ?? 0);
  
  if ($productId > 0) {
    try {
      $pdo->beginTransaction();
      
      switch ($action) {
        case 'update_price':
          $unitCost = (float)($_POST['unit_cost'] ?? 0);
          $markupPct = (float)($_POST['markup_pct'] ?? 0);
          $taxPct = (float)($_POST['tax_pct'] ?? 0);
          
          if ($unitCost > 0 && $markupPct >= 0 && $taxPct >= 0) {
            $stmt = $pdo->prepare('UPDATE products SET unit_cost = ?, markup_pct = ?, tax_pct = ? WHERE id = ?');
            $stmt->execute([$unitCost, $markupPct, $taxPct, $productId]);
            $message = 'Product pricing updated successfully!';
          } else {
            $error = 'Invalid pricing values.';
          }
          break;
          
        case 'update_stock':
          $quantity = (float)($_POST['quantity'] ?? 0);
          $movementType = $_POST['movement_type'] ?? 'in';
          $notes = trim($_POST['notes'] ?? 'Price/stock adjustment by admin');
          
          // Debug logging
          error_log("Stock update attempt - Product ID: $productId, Quantity: $quantity, Movement Type: $movementType, Notes: $notes");
          
          if ($quantity > 0) {
            // Add inventory movement
            $stmt = $pdo->prepare('INSERT INTO inventory_movements (product_id, movement_type, quantity, note, created_at) VALUES (?, ?, ?, ?, NOW())');
            $result = $stmt->execute([$productId, $movementType, $quantity, $notes]);
            
            if ($result) {
              $message = 'Stock updated successfully!';
              error_log("Stock update successful for product $productId");
            } else {
              $error = 'Failed to update stock in database.';
              error_log("Stock update failed for product $productId");
            }
          } else {
            $error = 'Invalid quantity value.';
            error_log("Invalid quantity value: $quantity");
          }
          break;
      }
      
      $pdo->commit();
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Failed to update: ' . $e->getMessage();
    }
  }
}

// Get all products with current stock
$products = $pdo->query("
  SELECT p.*, 
    COALESCE(SUM(CASE WHEN m.movement_type='in' THEN m.quantity ELSE -m.quantity END),0) AS current_stock
  FROM products p 
  LEFT JOIN inventory_movements m ON m.product_id = p.id 
  GROUP BY p.id 
  ORDER BY p.name
")->fetchAll();

// Helper function for price calculation
function compute_price(float $unitCost, float $markupPct, float $taxPct): float {
  $preTax = $unitCost + ($unitCost * ($markupPct / 100.0));
  $tax = $preTax * ($taxPct / 100.0);
  return round($preTax + $tax, 2);
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Product Management</h3>
        <div class="d-flex gap-2">
          <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Dashboard</a>
          <a href="products.php?preview=1" class="btn btn-outline-primary btn-sm">Shop View</a>
          <a href="admin_logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
        </div>
      </div>

      <?php if (isset($message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <?php echo htmlspecialchars($error); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Current Price</th>
                  <th>Stock</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                  <?php $currentPrice = compute_price((float)$product['unit_cost'], (float)$product['markup_pct'], (float)$product['tax_pct']); ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center">
                        <?php if ($product['image_path']): ?>
                          <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;margin-right:12px;">
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div>
                          <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="fw-semibold">₱<?php echo number_format($currentPrice, 2); ?></div>
                      <small class="text-muted">
                        Cost: ₱<?php echo number_format($product['unit_cost'], 2); ?> | 
                        Markup: <?php echo number_format($product['markup_pct'], 1); ?>% | 
                        Tax: <?php echo number_format($product['tax_pct'], 1); ?>%
                      </small>
                    </td>
                    <td>
                      <span class="badge bg-<?php echo $product['current_stock'] < 10 ? 'danger' : ($product['current_stock'] < 50 ? 'warning' : 'success'); ?>">
                        <?php echo number_format($product['current_stock'], 3); ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editPrice(<?php echo $product['id']; ?>, <?php echo $product['unit_cost']; ?>, <?php echo $product['markup_pct']; ?>, <?php echo $product['tax_pct']; ?>)">
                          <i class="bi bi-currency-dollar"></i> Edit Price
                        </button>
                        <button class="btn btn-outline-success" onclick="editStock(<?php echo $product['id']; ?>, <?php echo $product['current_stock']; ?>)">
                          <i class="bi bi-box"></i> Edit Stock
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Price Edit Modal -->
    <div class="modal fade" id="priceModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Product Price</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="post" id="priceForm">
            <div class="modal-body">
              <input type="hidden" name="action" value="update_price">
              <input type="hidden" name="product_id" id="priceProductId">
              
              <div class="mb-3">
                <label for="unitCost" class="form-label">Unit Cost (₱)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="unitCost" name="unit_cost" required>
                <div class="form-text">Base cost of the product</div>
              </div>
              
              <div class="mb-3">
                <label for="markupPct" class="form-label">Markup Percentage (%)</label>
                <input type="number" step="0.1" min="0" class="form-control" id="markupPct" name="markup_pct" required>
                <div class="form-text">Profit margin percentage</div>
              </div>
              
              <div class="mb-3">
                <label for="taxPct" class="form-label">Tax Percentage (%)</label>
                <input type="number" step="0.1" min="0" class="form-control" id="taxPct" name="tax_pct" required>
                <div class="form-text">Tax rate percentage</div>
              </div>
              
              <div class="alert alert-info">
                <strong>Final Price Preview:</strong> <span id="pricePreview">₱0.00</span>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Price</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Stock Edit Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Product Stock</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="post" id="stockForm">
            <div class="modal-body">
              <input type="hidden" name="action" value="update_stock">
              <input type="hidden" name="product_id" id="stockProductId">
              
              <div class="mb-3">
                <label for="movementType" class="form-label">Movement Type</label>
                <select class="form-select" id="movementType" name="movement_type" required>
                  <option value="in">Add Stock (+)</option>
                  <option value="out">Remove Stock (-)</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" step="0.001" min="0" class="form-control" id="quantity" name="quantity" required>
                <div class="form-text">Amount to add or remove from current stock</div>
              </div>
              
              <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2">Price/stock adjustment by admin</textarea>
              </div>
              
              <div class="alert alert-info">
                <strong>Current Stock:</strong> <span id="currentStock">0</span>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editPrice(productId, unitCost, markupPct, taxPct) {
      document.getElementById('priceProductId').value = productId;
      document.getElementById('unitCost').value = unitCost;
      document.getElementById('markupPct').value = markupPct;
      document.getElementById('taxPct').value = taxPct;
      updatePricePreview();
      new bootstrap.Modal(document.getElementById('priceModal')).show();
    }
    
    function editStock(productId, currentStock) {
      console.log('Opening stock edit modal for product:', productId, 'current stock:', currentStock);
      document.getElementById('stockProductId').value = productId;
      document.getElementById('currentStock').textContent = currentStock.toFixed(3);
      document.getElementById('quantity').value = '';
      new bootstrap.Modal(document.getElementById('stockModal')).show();
    }
    
    function updatePricePreview() {
      const unitCost = parseFloat(document.getElementById('unitCost').value) || 0;
      const markupPct = parseFloat(document.getElementById('markupPct').value) || 0;
      const taxPct = parseFloat(document.getElementById('taxPct').value) || 0;
      
      const preTax = unitCost + (unitCost * (markupPct / 100));
      const tax = preTax * (taxPct / 100);
      const finalPrice = preTax + tax;
      
      document.getElementById('pricePreview').textContent = '₱' + finalPrice.toFixed(2);
    }
    
    // Update price preview on input change
    document.getElementById('unitCost').addEventListener('input', updatePricePreview);
    document.getElementById('markupPct').addEventListener('input', updatePricePreview);
    document.getElementById('taxPct').addEventListener('input', updatePricePreview);
    
    // Add form submission debugging
    document.getElementById('stockForm').addEventListener('submit', function(e) {
      console.log('Stock form submitted');
      console.log('Product ID:', document.getElementById('stockProductId').value);
      console.log('Quantity:', document.getElementById('quantity').value);
      console.log('Movement Type:', document.getElementById('movementType').value);
      console.log('Notes:', document.getElementById('notes').value);
    });
    </script>
  </body>
</html>
