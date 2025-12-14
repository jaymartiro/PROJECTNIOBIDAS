<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
$pdo = get_user_pdo();

$orderNumber = trim($_GET['order'] ?? '');
$order = null;
$orderItems = [];
$statusHistory = [];

if ($orderNumber) {
  // Get order details
  $orderStmt = $pdo->prepare('SELECT * FROM user_orders WHERE order_number = ?');
  $orderStmt->execute([$orderNumber]);
  $order = $orderStmt->fetch();
  
  if ($order) {
    // Get order items
    $itemsStmt = $pdo->prepare('SELECT * FROM user_order_items WHERE user_order_id = ? ORDER BY id');
    $itemsStmt->execute([$order['id']]);
    $orderItems = $itemsStmt->fetchAll();
    
    // Get status history
    $historyStmt = $pdo->prepare('SELECT * FROM user_order_status_history WHERE user_order_id = ? ORDER BY created_at ASC');
    $historyStmt->execute([$order['id']]);
    $statusHistory = $historyStmt->fetchAll();
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Status - <?php echo htmlspecialchars($orderNumber); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Order Status</h3>
        <div class="d-flex gap-2">
          <a href="products.php" class="btn btn-outline-secondary btn-sm">Continue Shopping</a>
          <a href="index.php" class="btn btn-outline-secondary btn-sm">Home</a>
        </div>
      </div>

      <?php if (!$orderNumber): ?>
        <div class="card">
          <div class="card-body text-center">
            <h5>Track Your Order</h5>
            <p class="text-muted">Enter your order number to check the status</p>
            <form method="get" class="row g-3 justify-content-center">
              <div class="col-md-6">
                <input type="text" class="form-control" name="order" placeholder="Enter order number (e.g., ORD-20241201-0001)" required>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-primary">Track Order</button>
              </div>
            </form>
          </div>
        </div>
      <?php elseif (!$order): ?>
        <div class="alert alert-warning">
          <h5>Order Not Found</h5>
          <p>No order found with the number: <strong><?php echo htmlspecialchars($orderNumber); ?></strong></p>
          <p>Please check your order number and try again.</p>
          <a href="order_status.php" class="btn btn-primary">Try Another Order</a>
        </div>
      <?php else: ?>
        <!-- Order Found -->
        <div class="row g-4">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
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
                  ?> fs-6">
                    <?php echo ucfirst($order['status']); ?>
                  </span>
                </div>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <h6>Order Information</h6>
                    <table class="table table-sm">
                      <tr>
                        <td><strong>Order Date:</strong></td>
                        <td><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></td>
                      </tr>
                      <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td>
                          <span class="badge bg-<?php echo $order['payment_method'] === 'gcash' ? 'success' : 'info'; ?>">
                            <?php echo strtoupper($order['payment_method']); ?>
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td><strong>Total Amount:</strong></td>
                        <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                      </tr>
                      <tr>
                        <td><strong>Estimated Delivery:</strong></td>
                        <td>
                          <?php if ($order['estimated_delivery_date']): ?>
                            <span class="badge bg-info fs-6">
                              <i class="bi bi-calendar-check"></i> <?php echo date('F j, Y', strtotime($order['estimated_delivery_date'])); ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">Not set</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php if ($order['delivery_date']): ?>
                      <tr>
                        <td><strong>Actual Delivery:</strong></td>
                        <td><?php echo date('F j, Y', strtotime($order['delivery_date'])); ?></td>
                      </tr>
                      <?php endif; ?>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <h6>Delivery Information</h6>
                    <table class="table table-sm">
                      <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                      </tr>
                      <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                      </tr>
                      <?php if ($order['customer_phone']): ?>
                      <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                      </tr>
                      <?php endif; ?>
                      <tr>
                        <td><strong>Address:</strong></td>
                        <td><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></td>
                      </tr>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Order Items</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($orderItems as $item): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                          <td><?php echo number_format($item['quantity'], 3); ?></td>
                          <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                          <td>₱<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="3"><strong>Subtotal:</strong></td>
                        <td><strong>₱<?php echo number_format($order['subtotal'], 2); ?></strong></td>
                      </tr>
                      <tr>
                        <td colspan="3"><strong>Delivery Fee:</strong></td>
                        <td><strong>₱<?php echo number_format($order['delivery_fee'], 2); ?></strong></td>
                      </tr>
                      <tr class="table-active">
                        <td colspan="3"><strong>Total:</strong></td>
                        <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Order Progress</h5>
              </div>
              <div class="card-body">
                <?php if (empty($statusHistory)): ?>
                  <p class="text-muted">No status updates available.</p>
                <?php else: ?>
                  <div class="timeline">
                    <?php foreach ($statusHistory as $index => $history): ?>
                      <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                          <div class="bg-<?php 
                            echo match($history['status']) {
                              'pending' => 'warning',
                              'confirmed' => 'info',
                              'preparing' => 'primary',
                              'shipped' => 'secondary',
                              'delivered' => 'success',
                              'cancelled' => 'danger',
                              default => 'secondary'
                            };
                          ?> rounded-circle d-flex align-items-center justify-content-center" 
                               style="width: 40px; height: 40px;">
                            <i class="bi bi-<?php 
                              echo match($history['status']) {
                                'pending' => 'clock',
                                'confirmed' => 'check-circle',
                                'preparing' => 'gear',
                                'shipped' => 'truck',
                                'delivered' => 'check-circle-fill',
                                'cancelled' => 'x-circle',
                                default => 'circle'
                              };
                            ?> text-white"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                          <div class="d-flex justify-content-between align-items-start">
                            <div>
                              <h6 class="mb-1"><?php echo ucfirst($history['status']); ?></h6>
                              <?php if ($history['notes']): ?>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($history['notes']); ?></p>
                              <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></small>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if ($order['status'] === 'confirmed' && $order['estimated_delivery_date']): ?>
            <div class="col-12">
              <div class="alert alert-info">
                <h5><i class="bi bi-check-circle-fill"></i> Order Confirmed!</h5>
                <p class="mb-0">
                  <strong>Your order has been confirmed and is being prepared.</strong><br>
                  <i class="bi bi-truck"></i> <strong>Expected Delivery:</strong> <?php echo date('F j, Y', strtotime($order['estimated_delivery_date'])); ?>
                </p>
              </div>
            </div>
          <?php elseif ($order['status'] === 'preparing' && $order['estimated_delivery_date']): ?>
            <div class="col-12">
              <div class="alert alert-primary">
                <h5><i class="bi bi-gear-fill"></i> Order Being Prepared</h5>
                <p class="mb-0">
                  <strong>Your order is currently being prepared for shipping.</strong><br>
                  <i class="bi bi-truck"></i> <strong>Expected Delivery:</strong> <?php echo date('F j, Y', strtotime($order['estimated_delivery_date'])); ?>
                </p>
              </div>
            </div>
          <?php elseif ($order['status'] === 'shipped' && $order['estimated_delivery_date']): ?>
            <div class="col-12">
              <div class="alert alert-secondary">
                <h5><i class="bi bi-truck"></i> Order Shipped!</h5>
                <p class="mb-0">
                  <strong>Your order has been shipped and is on its way.</strong><br>
                  <i class="bi bi-calendar-check"></i> <strong>Expected Delivery:</strong> <?php echo date('F j, Y', strtotime($order['estimated_delivery_date'])); ?>
                </p>
              </div>
            </div>
          <?php elseif ($order['status'] === 'delivered'): ?>
            <div class="col-12">
              <div class="alert alert-success">
                <h5><i class="bi bi-check-circle-fill"></i> Order Delivered!</h5>
                <p class="mb-0">Your order has been successfully delivered. Thank you for your purchase!</p>
              </div>
            </div>
          <?php elseif ($order['status'] === 'cancelled'): ?>
            <div class="col-12">
              <div class="alert alert-danger">
                <h5><i class="bi bi-x-circle-fill"></i> Order Cancelled</h5>
                <p class="mb-0">This order has been cancelled. If you have any questions, please contact our support.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
