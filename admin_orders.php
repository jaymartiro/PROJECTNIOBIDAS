<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}

$pdo = get_admin_pdo();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $orderId = (int)($_POST['order_id'] ?? 0);
  $action = $_POST['action'];
  $notes = trim($_POST['notes'] ?? '');
  $postedDeliveryFee = isset($_POST['delivery_fee']) ? trim($_POST['delivery_fee']) : '';
  $postedDeliveryDate = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : '';
  
  if ($orderId > 0) {
    try {
      $pdo->beginTransaction();
      
      // Get current order
      $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
      $orderStmt->execute([$orderId]);
      $order = $orderStmt->fetch();
      
      if ($order) {
        $newStatus = '';
        $deliveryDate = null;
        
        switch ($action) {
          case 'confirm':
            $newStatus = 'confirmed';
            $notes = $notes ?: 'Order confirmed by admin - Expected delivery: ' . date('F j, Y', strtotime('+3 weekdays'));
            // Use custom delivery date if provided, otherwise default to 3 weekdays
            if ($postedDeliveryDate) {
              $estimatedDelivery = date('Y-m-d', strtotime($postedDeliveryDate));
            } else {
              $estimatedDelivery = date('Y-m-d', strtotime('+3 weekdays'));
            }
            break;
          case 'preparing':
            $newStatus = 'preparing';
            $notes = $notes ?: 'Order is being prepared';
            // Keep existing estimated delivery date or set new one if provided
            if ($postedDeliveryDate) {
              $estimatedDelivery = date('Y-m-d', strtotime($postedDeliveryDate));
            } else {
              $estimatedDelivery = $order['estimated_delivery_date']; // Keep existing
            }
            break;
          case 'ship':
            $newStatus = 'shipped';
            $notes = $notes ?: 'Order has been shipped - Expected delivery: ' . date('F j, Y', strtotime('+1 weekday'));
            // Use custom delivery date if provided, otherwise default to 1 weekday
            if ($postedDeliveryDate) {
              $estimatedDelivery = date('Y-m-d', strtotime($postedDeliveryDate));
            } else {
              $estimatedDelivery = date('Y-m-d', strtotime('+1 weekday'));
            }
            break;
          case 'deliver':
            $newStatus = 'delivered';
            $deliveryDate = date('Y-m-d');
            $notes = $notes ?: 'Order delivered successfully';
            // No estimated delivery date needed for delivered orders
            $estimatedDelivery = null;
            break;
          case 'cancel':
            $newStatus = 'cancelled';
            $notes = $notes ?: 'Order cancelled';
            // No estimated delivery date for cancelled orders
            $estimatedDelivery = null;
            break;
        }
        
        if ($newStatus) {
          // Update order status and delivery date in admin DB
          $newDeliveryFee = is_numeric($postedDeliveryFee) ? (float)$postedDeliveryFee : (float)$order['delivery_fee'];
          $updateStmt = $pdo->prepare('UPDATE orders SET status = ?, delivery_date = ?, estimated_delivery_date = ?, admin_notes = ?, delivery_fee = ? WHERE id = ?');
          $updateStmt->execute([$newStatus, $deliveryDate, $estimatedDelivery ?? $order['estimated_delivery_date'], $notes, $newDeliveryFee, $orderId]);
          
          // Add to status history (admin DB)
          $historyStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)');
          $historyStmt->execute([$orderId, $newStatus, $notes]);

          // Also sync status to user database so the user is notified and sees the ETA
          try {
            require_once __DIR__ . '/user_config.php';
            $userPdo = get_user_pdo();

            // Update user_orders by order_number (sync delivery_fee too)
            $userUpdate = $userPdo->prepare('UPDATE user_orders SET status = ?, delivery_date = ?, estimated_delivery_date = ?, delivery_fee = ? WHERE order_number = ?');
            $userUpdate->execute([
              $newStatus,
              $deliveryDate,
              $estimatedDelivery ?? $order['estimated_delivery_date'],
              $newDeliveryFee,
              $order['order_number']
            ]);

            // Fetch user_order_id for history insert
            $uoIdStmt = $userPdo->prepare('SELECT id FROM user_orders WHERE order_number = ? LIMIT 1');
            $uoIdStmt->execute([$order['order_number']]);
            $userOrderId = (int)$uoIdStmt->fetchColumn();

            if ($userOrderId > 0) {
              $userHist = $userPdo->prepare('INSERT INTO user_order_status_history (user_order_id, status, notes) VALUES (?, ?, ?)');
              $userHist->execute([$userOrderId, $newStatus, $notes]);
            }
          } catch (Throwable $syncEx) {
            // Swallow sync errors to avoid blocking admin flow; user may not see update if this fails
          }
        }
      }
      
      $pdo->commit();
      header('Location: admin_orders.php?updated=1');
      exit;
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Failed to update order: ' . $e->getMessage();
    }
  }
}

// Get orders with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$whereConditions = [];
$params = [];

if ($statusFilter) {
  $whereConditions[] = 'o.status = ?';
  $params[] = $statusFilter;
}

if ($search) {
  $whereConditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
  $searchTerm = "%{$search}%";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM orders o {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Get orders
$ordersSql = "SELECT o.*, 
              (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
              FROM orders o 
              {$whereClause}
              ORDER BY o.created_at DESC 
              LIMIT {$limit} OFFSET {$offset}";
$ordersStmt = $pdo->prepare($ordersSql);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

// Get status counts for dashboard
$statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status")->fetchAll();
$statusCounts = array_column($statusCounts, 'count', 'status');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Order Management</h3>
        <div class="d-flex gap-2">
          <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Dashboard</a>
          <a href="admin_logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
        </div>
      </div>

      <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
          Order status updated successfully!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- Status Overview -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Pending</div>
              <div class="h4 text-warning"><?php echo $statusCounts['pending'] ?? 0; ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Confirmed</div>
              <div class="h4 text-info"><?php echo $statusCounts['confirmed'] ?? 0; ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Preparing</div>
              <div class="h4 text-primary"><?php echo $statusCounts['preparing'] ?? 0; ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Shipped</div>
              <div class="h4 text-secondary"><?php echo $statusCounts['shipped'] ?? 0; ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Delivered</div>
              <div class="h4 text-success"><?php echo $statusCounts['delivered'] ?? 0; ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body">
              <div class="text-muted">Cancelled</div>
              <div class="h4 text-danger"><?php echo $statusCounts['cancelled'] ?? 0; ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="get" class="row g-3">
            <div class="col-md-4">
              <label for="status" class="form-label">Status Filter</label>
              <select class="form-select" id="status" name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="preparing" <?php echo $statusFilter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="search" class="form-label">Search</label>
              <input type="text" class="form-control" id="search" name="search" 
                     placeholder="Order number, customer name, or email" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">&nbsp;</label>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">Filter</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Items</th>
                  <th>Address</th>
                  <th>Delivery Fee</th>
                  <th>Total</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td>
                      <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    </td>
                    <td>
                      <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                    </td>
                    <td><?php echo (int)$order['item_count']; ?> items</td>
                    <td>
                      <small class="text-muted d-block"><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></small>
                    </td>
                    <td>₱<?php echo number_format((float)$order['delivery_fee'], 2); ?></td>
                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                    <td>
                      <?php
                        $method = strtoupper($order['payment_method']);
                        $isPaid = false; $hasReceipt = false;
                        if ($order['payment_method'] === 'gcash' && !empty($order['payment_details'])) {
                          $pd = json_decode($order['payment_details'], true);
                          if (is_array($pd)) {
                            $hasReceipt = !empty($pd['receipt']);
                            $isPaid = $hasReceipt && isset($pd['amount']) && abs(((float)$pd['amount']) - ((float)$order['total_amount'])) < 0.01;
                          }
                        }
                      ?>
                      <span class="badge bg-<?php echo $order['payment_method'] === 'gcash' ? 'success' : 'info'; ?>"><?php echo $method; ?></span>
                      <?php if ($order['payment_method'] === 'gcash'): ?>
                        <span class="badge bg-<?php echo $isPaid ? 'primary' : ($hasReceipt ? 'warning' : 'secondary'); ?>">
                          <?php echo $isPaid ? 'Paid' : ($hasReceipt ? 'Needs review' : 'No receipt'); ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-<?php 
                        echo match($order['status']) {
                          'pending' => 'warning',
                          'confirmed' => 'info',
                          'preparing' => 'primary',
                          'shipped' => 'secondary',
                          'delivered' => 'success',
                          'cancelled' => 'danger',
                          default => 'secondary'
                        };
                      ?>">
                        <?php echo ucfirst($order['status']); ?>
                      </span>
                    </td>
                    <td>
                      <div><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                      <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" 
                                onclick="viewOrder(<?php echo $order['id']; ?>)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($order['status'] === 'pending'): ?>
                          <button type="button" class="btn btn-outline-success" 
                                  onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'confirm')">
                            <i class="bi bi-check"></i>
                          </button>
                        <?php endif; ?>
                        <?php if (in_array($order['status'], ['confirmed', 'preparing', 'shipped'])): ?>
                          <button type="button" class="btn btn-outline-warning" 
                                  onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancel')">
                            <i class="bi bi-x"></i>
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if (empty($orders)): ?>
            <div class="text-center py-4">
              <p class="text-muted">No orders found.</p>
            </div>
          <?php endif; ?>

          <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
            <nav aria-label="Orders pagination">
              <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>">
                      <?php echo $i; ?>
                    </a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Order Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="orderDetails">
            <!-- Order details will be loaded here -->
          </div>
        </div>
      </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Update Order Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="post" id="statusForm">
            <div class="modal-body">
              <input type="hidden" name="order_id" id="statusOrderId">
              <input type="hidden" name="action" id="statusAction">
              <div class="mb-3">
                <label for="statusNotes" class="form-label">Notes (Optional)</label>
                <textarea class="form-control" id="statusNotes" name="notes" rows="3"></textarea>
              </div>
              <div class="mb-3">
                <label for="deliveryFee" class="form-label">Delivery Fee (₱)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="deliveryFee" name="delivery_fee" placeholder="Enter delivery fee based on address distance">
                <div class="form-text">Set smaller fee for nearby addresses; higher for far locations.</div>
              </div>
              <div class="mb-3">
                <label for="deliveryDate" class="form-label">Expected Delivery Date</label>
                <input type="date" class="form-control" id="deliveryDate" name="delivery_date" min="<?php echo date('Y-m-d'); ?>">
                <div class="form-text">Set the expected delivery date for this order. Leave empty to use default (3 days for confirmed, 1 day for shipped).</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewOrder(orderId) {
      fetch(`admin_order_details.php?id=${orderId}`)
        .then(response => response.text())
        .then(html => {
          document.getElementById('orderDetails').innerHTML = html;
          new bootstrap.Modal(document.getElementById('orderModal')).show();
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to load order details');
        });
    }

    function updateOrderStatus(orderId, action) {
      document.getElementById('statusOrderId').value = orderId;
      document.getElementById('statusAction').value = action;
      
      // Set default delivery date based on action
      const deliveryDateInput = document.getElementById('deliveryDate');
      const today = new Date();
      
      if (action === 'confirm') {
        // Default to 3 days from today
        const deliveryDate = new Date(today);
        deliveryDate.setDate(today.getDate() + 3);
        deliveryDateInput.value = deliveryDate.toISOString().split('T')[0];
      } else if (action === 'ship') {
        // Default to 1 day from today
        const deliveryDate = new Date(today);
        deliveryDate.setDate(today.getDate() + 1);
        deliveryDateInput.value = deliveryDate.toISOString().split('T')[0];
      } else {
        // Clear for other actions
        deliveryDateInput.value = '';
      }
      
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    }
    </script>
  </body>
</html>
