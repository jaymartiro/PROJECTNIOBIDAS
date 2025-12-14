<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$pdo = get_user_pdo();

// List user's recent orders paid via GCash
$stmt = $pdo->prepare("SELECT order_number, total_amount, payment_method, payment_details, status, created_at FROM user_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Payments</h3>
        <div class="d-flex gap-2">
          <a href="products.php" class="btn btn-secondary btn-sm">Shop</a>
          <a href="cart.php" class="btn btn-outline-primary btn-sm">Cart</a>
          <a href="profile.php" class="btn btn-outline-dark btn-sm">Profile</a>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Receipt</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <?php $pd = $o['payment_details'] ? json_decode($o['payment_details'], true) : null; ?>
                  <?php $hasReceipt = is_array($pd) && !empty($pd['receipt']); ?>
                  <?php $amountPaid = is_array($pd) && isset($pd['amount']) ? (float)$pd['amount'] : 0.0; ?>
                  <?php $isPaid = $hasReceipt && abs($amountPaid - (float)$o['total_amount']) < 0.01; ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong></td>
                    <td>₱<?php echo number_format($o['total_amount'],2); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $o['payment_method']==='gcash'?'success':'info'; ?>"><?php echo strtoupper($o['payment_method']); ?></span>
                      <?php if ($o['payment_method']==='gcash'): ?>
                        <span class="badge bg-<?php echo $isPaid?'primary':($hasReceipt?'warning':'secondary'); ?>"><?php echo $isPaid?'Paid':($hasReceipt?'Under review':'No receipt'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?php echo match($o['status']){ 'pending'=>'warning','confirmed'=>'info','preparing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger', default=>'secondary'}; ?>"><?php echo ucfirst($o['status']); ?></span></td>
                    <td>
                      <?php if ($hasReceipt): ?>
                        <a href="<?php echo htmlspecialchars($pd['receipt']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?php echo date('M j, Y', strtotime($o['created_at'])); ?></div>
                      <small class="text-muted"><?php echo date('g:i A', strtotime($o['created_at'])); ?></small>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>


