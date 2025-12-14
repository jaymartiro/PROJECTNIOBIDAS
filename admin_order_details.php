<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  exit('Access denied');
}

$pdo = get_admin_pdo();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
  http_response_code(400);
  exit('Invalid order ID');
}

// Get order details
$orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
  http_response_code(404);
  exit('Order not found');
}

// Get order items
$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Get status history
$historyStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC');
$historyStmt->execute([$orderId]);
$statusHistory = $historyStmt->fetchAll();
?>

<div class="row">
  <div class="col-md-6">
    <h6>Order Information</h6>
    <table class="table table-sm">
      <tr>
        <td><strong>Order Number:</strong></td>
        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
      </tr>
      <tr>
        <td><strong>Status:</strong></td>
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
      </tr>
      <tr>
        <td><strong>Order Date:</strong></td>
        <td><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></td>
      </tr>
      <tr>
        <td><strong>Estimated Delivery:</strong></td>
        <td>
          <?php if ($order['estimated_delivery_date']): ?>
            <span class="badge bg-info">
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
    <h6>Customer Information</h6>
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

<div class="row mt-3">
  <div class="col-md-6">
    <h6>Payment Information</h6>
    <table class="table table-sm">
      <tr>
        <td><strong>Method:</strong></td>
        <td>
          <span class="badge bg-<?php echo $order['payment_method'] === 'gcash' ? 'success' : 'info'; ?>">
            <?php echo strtoupper($order['payment_method']); ?>
          </span>
        </td>
      </tr>
      <?php if ($order['payment_details']): ?>
        <?php $pd = json_decode($order['payment_details'], true); ?>
        <?php if (is_array($pd)) : ?>
          <tr>
            <td><strong>Reference #:</strong></td>
            <td><?php echo htmlspecialchars($pd['reference'] ?? ''); ?></td>
          </tr>
          <tr>
            <td><strong>Amount:</strong></td>
            <td>₱<?php echo isset($pd['amount']) ? number_format((float)$pd['amount'], 2) : '0.00'; ?></td>
          </tr>
          <?php if (!empty($pd['receipt'])): ?>
          <tr>
            <td><strong>Receipt:</strong></td>
            <td>
              <a href="<?php echo htmlspecialchars($pd['receipt']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Receipt</a>
            </td>
          </tr>
          <?php endif; ?>
        <?php else: ?>
          <tr>
            <td><strong>Details:</strong></td>
            <td><?php echo htmlspecialchars($order['payment_details']); ?></td>
          </tr>
        <?php endif; ?>
      <?php endif; ?>
    </table>
  </div>
  
  <div class="col-md-6">
    <h6>Order Summary</h6>
    <table class="table table-sm">
      <tr>
        <td><strong>Subtotal:</strong></td>
        <td>₱<?php echo number_format($order['subtotal'], 2); ?></td>
      </tr>
      <tr>
        <td><strong>Delivery Fee:</strong></td>
        <td>₱<?php echo number_format($order['delivery_fee'], 2); ?></td>
      </tr>
      <tr class="table-active">
        <td><strong>Total:</strong></td>
        <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
      </tr>
    </table>
  </div>
</div>

<div class="mt-3">
  <h6>Order Items</h6>
  <div class="table-responsive">
    <table class="table table-sm">
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
    </table>
  </div>
</div>

<?php if ($order['admin_notes']): ?>
<div class="mt-3">
  <h6>Admin Notes</h6>
  <div class="alert alert-info">
    <?php echo nl2br(htmlspecialchars($order['admin_notes'])); ?>
  </div>
</div>
<?php endif; ?>

<div class="mt-3">
  <h6>Status History</h6>
  <div class="timeline">
    <?php foreach ($statusHistory as $history): ?>
      <div class="d-flex mb-2">
        <div class="flex-shrink-0">
          <span class="badge bg-<?php 
            echo match($history['status']) {
              'pending' => 'warning',
              'confirmed' => 'info',
              'preparing' => 'primary',
              'shipped' => 'secondary',
              'delivered' => 'success',
              'cancelled' => 'danger',
              default => 'secondary'
            };
          ?>">
            <?php echo ucfirst($history['status']); ?>
          </span>
        </div>
        <div class="flex-grow-1 ms-3">
          <div class="small text-muted"><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></div>
          <?php if ($history['notes']): ?>
            <div class="small"><?php echo htmlspecialchars($history['notes']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="mt-4">
  <h6>Quick Actions</h6>
  <div class="btn-group">
    <?php if ($order['status'] === 'pending'): ?>
      <button type="button" class="btn btn-success btn-sm" onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'confirm')">
        <i class="bi bi-check"></i> Confirm Order
      </button>
    <?php endif; ?>
    
    <?php if ($order['status'] === 'confirmed'): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'preparing')">
        <i class="bi bi-gear"></i> Start Preparing
      </button>
    <?php endif; ?>
    
    <?php if ($order['status'] === 'preparing'): ?>
      <button type="button" class="btn btn-secondary btn-sm" onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'ship')">
        <i class="bi bi-truck"></i> Mark as Shipped
      </button>
    <?php endif; ?>
    
    <?php if ($order['status'] === 'shipped'): ?>
      <button type="button" class="btn btn-success btn-sm" onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'deliver')">
        <i class="bi bi-check-circle"></i> Mark as Delivered
      </button>
    <?php endif; ?>
    
    <?php if (in_array($order['status'], ['pending', 'confirmed', 'preparing'])): ?>
      <button type="button" class="btn btn-danger btn-sm" onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'cancel')">
        <i class="bi bi-x-circle"></i> Cancel Order
      </button>
    <?php endif; ?>
  </div>
</div>

<script>
function quickUpdateStatus(orderId, action) {
  if (confirm('Are you sure you want to update this order status?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin_orders.php';
    
    const orderIdInput = document.createElement('input');
    orderIdInput.type = 'hidden';
    orderIdInput.name = 'order_id';
    orderIdInput.value = orderId;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    
    form.appendChild(orderIdInput);
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
  }
}
</script>
