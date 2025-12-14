<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
$pdo = get_user_pdo();
$ownerKey = $_SESSION['user_id'] ?? session_id();

// Get open cart and totals
$cartIdStmt = $pdo->prepare("SELECT id FROM carts WHERE owner_key=? AND status='open' ORDER BY id DESC LIMIT 1");
$cartIdStmt->execute([(string)$ownerKey]);
$cartId = (int)$cartIdStmt->fetchColumn();
if ($cartId <= 0) { header('Location: cart.php'); exit; }

// Sample products data for checkout display
$sampleProducts = [
  1 => ['id' => 1, 'name' => 'Pork Longanisa'],
  2 => ['id' => 2, 'name' => 'Longanisa Hamonada'],
  3 => ['id' => 3, 'name' => 'Pork Tocino'],
  4 => ['id' => 4, 'name' => 'Chicken Hotdog'],
  5 => ['id' => 5, 'name' => 'Chicken Cheese Dog']
];

// Get cart items without product join
$items = $pdo->prepare('SELECT ci.qty, ci.unit_price, ci.product_id, ci.product_name FROM cart_items ci WHERE ci.cart_id=?');
$items->execute([$cartId]);
$cartItems = $items->fetchAll();

// Enhance cart items with product data
foreach ($cartItems as &$item) {
  $productId = (int)$item['product_id'];
  if (isset($sampleProducts[$productId])) {
    $item['name'] = $sampleProducts[$productId]['name'];
  } else {
    // Fallback for unknown products
    $item['name'] = $item['product_name'] ?? 'Unknown Product';
  }
}
$subtotal = 0.0; foreach ($cartItems as $ci) { $subtotal += ((float)$ci['unit_price']) * ((float)$ci['qty']); }
$delivery = count($cartItems) ? 50.00 : 0.00; $total = $subtotal + $delivery;

$orderPlaced = false; $errors = []; $orderNumber = '';

// Get user info if logged in
$userInfo = null;
if (!empty($_SESSION['user_id'])) {
  $userStmt = $pdo->prepare('SELECT name, email, phone, address FROM users WHERE id = ?');
  $userStmt->execute([$_SESSION['user_id']]);
  $userInfo = $userStmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $method = $_POST['method'] ?? '';
  $customerName = trim($_POST['customer_name'] ?? '');
  $customerEmail = trim($_POST['customer_email'] ?? '');
  $customerPhone = trim($_POST['customer_phone'] ?? '');
  $customerAddress = trim($_POST['customer_address'] ?? '');
  $paymentDetails = trim($_POST['payment_details'] ?? '');
  $amountPaid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0.0;
  $gcashReceiptPath = '';
  
  // Validation
  if ($method !== 'gcash' && $method !== 'cod') { $errors[] = 'Please choose a payment method.'; }
  if (empty($customerName)) { $errors[] = 'Customer name is required.'; }
  if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email address is required.'; }
  if (empty($customerAddress)) { $errors[] = 'Delivery address is required.'; }
  if ($method === 'gcash') {
    // STRICT GCASH VALIDATION - No shortcuts allowed!
    require_once __DIR__ . '/payment_validation.php';
    
    // 1. Reference Number Validation
    $refErrors = PaymentValidator::validateReferenceNumber($paymentDetails);
    $errors = array_merge($errors, $refErrors);
    
    // Check for duplicate reference numbers
    if (empty($refErrors)) {
      $dupErrors = PaymentValidator::checkDuplicateReference($pdo, $paymentDetails);
      $errors = array_merge($errors, $dupErrors);
    }
    
    // 2. Amount Validation - EXACT MATCH REQUIRED
    $amountErrors = PaymentValidator::validateAmount($amountPaid, $total);
    $errors = array_merge($errors, $amountErrors);
    
    // 3. Receipt Image Validation - STRICT GCASH EXPRESS SEND VALIDATION
    $receiptErrors = PaymentSecurity::validateGCashExpressSend($_FILES['gcash_receipt'] ?? null, $paymentDetails, $amountPaid);
    $errors = array_merge($errors, $receiptErrors);
    
    // 4. Additional Security Checks
    if (empty($errors)) {
      $securityCheck = PaymentSecurity::checkSuspiciousPatterns($paymentDetails, $amountPaid, $customerEmail);
      if ($securityCheck['suspicious']) {
        $errors[] = 'Payment appears suspicious: ' . implode(', ', $securityCheck['reasons']) . '. Please contact support.';
      }
    }
    
    // 5. Save receipt if all validations pass
    if (empty($errors) && isset($_FILES['gcash_receipt']) && $_FILES['gcash_receipt']['error'] === UPLOAD_ERR_OK) {
      $mime = mime_content_type($_FILES['gcash_receipt']['tmp_name']);
      $filename = PaymentValidator::generateReceiptFilename($paymentDetails, $mime);
      
      $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
      if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
      
      $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
      
      if (!@move_uploaded_file($_FILES['gcash_receipt']['tmp_name'], $dest)) {
        $errors[] = 'Failed to save receipt image. Please try again.';
      } else {
        $gcashReceiptPath = 'uploads/receipts/' . $filename;
        
        // Log the payment attempt for admin review
        PaymentValidator::logPaymentAttempt($orderNumber ?? 'TEMP', $paymentDetails, $amountPaid, $gcashReceiptPath, $customerEmail);
      }
    }
  }
  
  if (!$errors) {
    try {
      $pdo->beginTransaction();
      
      // Generate order number
      $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
      
      // Calculate estimated delivery date (3-5 business days from order confirmation)
      // For now, set to 3 business days from order date, but this will be updated when admin confirms
      $estimatedDelivery = date('Y-m-d', strtotime('+3 weekdays'));
      
      // Create order in user database
      $orderStmt = $pdo->prepare('INSERT INTO user_orders (order_number, user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, payment_details, subtotal, delivery_fee, total_amount, estimated_delivery_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $orderStmt->execute([
        $orderNumber,
        !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        $customerName,
        $customerEmail,
        $customerPhone,
        $customerAddress,
        $method,
        $method === 'gcash' ? json_encode(['reference'=>$paymentDetails, 'amount'=>$amountPaid, 'receipt'=>$gcashReceiptPath]) : $paymentDetails,
        $subtotal,
        $delivery,
        $total,
        $estimatedDelivery
      ]);
      
      $userOrderId = $pdo->lastInsertId();
      
      // Also create order in admin database for admin management
      require_once __DIR__ . '/admin_config.php';
      $adminPdo = get_admin_pdo();
      
      $adminOrderStmt = $adminPdo->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, payment_details, subtotal, delivery_fee, total_amount, estimated_delivery_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $adminOrderStmt->execute([
        $orderNumber,
        !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        $customerName,
        $customerEmail,
        $customerPhone,
        $customerAddress,
        $method,
        $method === 'gcash' ? json_encode(['reference'=>$paymentDetails, 'amount'=>$amountPaid, 'receipt'=>$gcashReceiptPath]) : $paymentDetails,
        $subtotal,
        $delivery,
        $total,
        $estimatedDelivery
      ]);
      
      $adminOrderId = $adminPdo->lastInsertId();
      
      // Create order items in user database
      $orderItemStmt = $pdo->prepare('INSERT INTO user_order_items (user_order_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($cartItems as $item) {
        $itemTotal = ((float)$item['unit_price']) * ((float)$item['qty']);
        $orderItemStmt->execute([
          $userOrderId,
          $item['product_id'],
          $item['name'],
          $item['qty'],
          $item['unit_price'],
          $itemTotal
        ]);
      }
      
      // Create order items in admin database
      $adminOrderItemStmt = $adminPdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($cartItems as $item) {
        $itemTotal = ((float)$item['unit_price']) * ((float)$item['qty']);
        $adminOrderItemStmt->execute([
          $adminOrderId,
          $item['product_id'],
          $item['name'],
          $item['qty'],
          $item['unit_price'],
          $itemTotal
        ]);
      }
      
      // Add initial status to history in user database
      $statusStmt = $pdo->prepare('INSERT INTO user_order_status_history (user_order_id, status, notes) VALUES (?, ?, ?)');
      $statusStmt->execute([$userOrderId, 'pending', 'Order placed successfully']);
      
      // Add initial status to history in admin database
      $adminStatusStmt = $adminPdo->prepare('INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)');
      $adminStatusStmt->execute([$adminOrderId, 'pending', 'Order placed successfully']);
      
      // Reduce stock in admin database for each ordered item
      foreach ($cartItems as $item) {
        $stockStmt = $adminPdo->prepare('INSERT INTO inventory_movements (product_id, movement_type, quantity, note, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stockStmt->execute([
          $item['product_id'],
          'out',
          $item['qty'],
          'Stock reduced for order: ' . $orderNumber
        ]);
      }
      
      // Mark cart as checked out
    $upd = $pdo->prepare("UPDATE carts SET status='checked_out' WHERE id=?");
    $upd->execute([$cartId]);
      
      $pdo->commit();
      $orderPlaced = true;
      
    } catch (Exception $e) {
      $pdo->rollBack();
      $errors[] = 'Failed to place order: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Checkout</h3>
        <div class="d-flex gap-2">
          <a href="cart.php" class="btn btn-outline-secondary btn-sm">Back to cart</a>
          <a href="index.php" class="btn btn-outline-secondary btn-sm">Home</a>
        </div>
      </div>

      <?php if ($orderPlaced): ?>
        <div class="alert alert-success">
          <h4>Order Placed Successfully!</h4>
          <p><strong>Order Number:</strong> <?php echo htmlspecialchars($orderNumber); ?></p>
          <p><strong>Total Amount:</strong> ₱<?php echo number_format($total,2); ?></p>
          <p><strong>Payment Method:</strong> <?php echo ucfirst($_POST['method'] ?? ''); ?></p>
          <p><strong>Estimated Delivery:</strong> <?php echo date('F j, Y', strtotime($_POST['estimated_delivery'] ?? '+3 weekdays')); ?></p>
          <hr>
          <p>Your order has been received and is being processed. You will receive a confirmation email shortly.</p>
          <p>The admin will review your order and confirm it within 24 hours.</p>
        </div>
        <div class="d-flex gap-2">
        <a href="products.php" class="btn btn-primary">Continue Shopping</a>
          <a href="order_status.php?order=<?php echo urlencode($orderNumber); ?>" class="btn btn-outline-primary">Track Order</a>
        </div>
      <?php else: ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php echo htmlspecialchars(implode('<br>', $errors)); ?></div><?php endif; ?>
        <div class="row g-3">
          <div class="col-12 col-lg-7">
            <form method="post" enctype="multipart/form-data">
              <!-- Customer Information -->
              <div class="card mb-3">
                <div class="card-body">
                  <h5 class="card-title">Customer Information</h5>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="customer_name" class="form-label">Full Name *</label>
                      <input type="text" class="form-control" id="customer_name" name="customer_name" 
                             value="<?php echo htmlspecialchars($userInfo['name'] ?? $_POST['customer_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label for="customer_email" class="form-label">Email Address *</label>
                      <input type="email" class="form-control" id="customer_email" name="customer_email" 
                             value="<?php echo htmlspecialchars($userInfo['email'] ?? $_POST['customer_email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label for="customer_phone" class="form-label">Phone Number</label>
                      <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                             value="<?php echo htmlspecialchars($userInfo['phone'] ?? $_POST['customer_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                      <label for="customer_address" class="form-label">Delivery Address *</label>
                      <textarea class="form-control" id="customer_address" name="customer_address" rows="3" required><?php echo htmlspecialchars($userInfo['address'] ?? $_POST['customer_address'] ?? ''); ?></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Payment Method -->
            <div class="card">
              <div class="card-body">
                  <h5 class="card-title">Payment Method</h5>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="method" id="gcash" value="gcash" 
                           <?php echo ($_POST['method'] ?? '') === 'gcash' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="gcash">
                      <strong>GCash</strong>
                      <div class="text-muted small">Pay via GCash mobile payment</div>
                    </label>
                  </div>
                  <div id="gcash-details" class="mb-3" style="display: none;">
                    <?php
                      // Locate admin-provided QR image if available (png/jpg/jpeg/webp)
                      $qrFilenames = ['gcash_qr.png','gcash_qr.jpg','gcash_qr.jpeg','gcash_qr.webp'];
                      $qrSrc = '';
                      foreach ($qrFilenames as $fn) {
                        $p = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $fn;
                        if (file_exists($p)) { $qrSrc = 'images/' . $fn; break; }
                      }
                      if ($qrSrc === '') { $qrSrc = 'https://i.imgur.com/2nV8FQv.png'; }
                    ?>
                    <div class="mb-3 text-center">
                      <img src="<?php echo htmlspecialchars($qrSrc, ENT_QUOTES); ?>" alt="GCash QR" style="max-width:260px;width:100%;height:auto;border:1px solid #e5e7eb;border-radius:8px;background:#fff">
                      <div class="small text-muted mt-2">Scan this QR with GCash and pay the exact total.</div>
                    </div>
                    <label for="payment_details" class="form-label">GCash Reference Number *</label>
                    <input type="text" class="form-control" id="payment_details" name="payment_details" 
                           placeholder="Enter GCash reference number" 
                           value="<?php echo htmlspecialchars($_POST['payment_details'] ?? ''); ?>">
                    <div class="form-text">
                      <strong>Amount to Send:</strong> ₱<?php echo number_format($total,2); ?><br>
                      <strong class="text-danger">⚠️ STRICT VALIDATION:</strong> Only legitimate GCash Express Send receipts are accepted. Fake receipts will be rejected.<br>
                      <strong class="text-warning">📱 REQUIREMENTS:</strong> Upload your actual GCash Express Send receipt from the GCash app.
                    </div>
                    <div class="mt-3">
                      <label for="amount_paid" class="form-label">Amount Paid (₱) *</label>
                      <input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" value="<?php echo htmlspecialchars($_POST['amount_paid'] ?? ''); ?>">
                    </div>
                    <div class="mt-3">
                      <label for="gcash_receipt" class="form-label">Upload GCash Express Send Receipt (JPG/PNG, max 5MB) *</label>
                      <input type="file" class="form-control" id="gcash_receipt" name="gcash_receipt" accept="image/jpeg,image/png">
                      <div class="form-text">
                        <strong>📱 IMPORTANT:</strong> Only upload your actual GCash Express Send receipt from the GCash app. Screenshots, random images, or fake receipts will be rejected.
                      </div>
                    </div>
                  </div>
                  
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="method" id="cod" value="cod" 
                           <?php echo ($_POST['method'] ?? '') === 'cod' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="cod">
                      <strong>Cash on Delivery (COD)</strong>
                      <div class="text-muted small">Pay when your order arrives</div>
                    </label>
                  </div>
                  <div id="cod-details" class="mb-3" style="display: none;">
                    <div class="alert alert-info">
                      <strong>Cash on Delivery Information:</strong><br>
                      • Payment will be collected upon delivery<br>
                      • Please prepare exact amount: ₱<?php echo number_format($total,2); ?><br>
                      • Delivery fee is included in the total amount
                    </div>
                  </div>
                  
                  <button class="btn btn-danger btn-lg w-100" type="submit">Confirm and Place Order</button>
              </div>
            </div>
            </form>
          </div>
          <div class="col-12 col-lg-5">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title">Order Summary</h5>
                <ul class="list-unstyled">
                  <?php foreach ($cartItems as $i): ?>
                    <li class="d-flex justify-content-between"><span><?php echo htmlspecialchars($i['name']); ?> × <?php echo htmlspecialchars($i['qty']); ?></span><span>₱<?php echo number_format(((float)$i['unit_price'])*((float)$i['qty']),2); ?></span></li>
                  <?php endforeach; ?>
                </ul>
                <div class="d-flex justify-content-between"><span>Items:</span><strong>₱<?php echo number_format($subtotal,2); ?></strong></div>
                <div class="d-flex justify-content-between"><span>Delivery:</span><strong>₱<?php echo number_format($delivery,2); ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between"><span>Total:</span><strong>₱<?php echo number_format($total,2); ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
    
    <script>
    // Handle payment method selection
    document.addEventListener('DOMContentLoaded', function() {
      const gcashRadio = document.getElementById('gcash');
      const codRadio = document.getElementById('cod');
      const gcashDetails = document.getElementById('gcash-details');
      const codDetails = document.getElementById('cod-details');
      
      function togglePaymentDetails() {
        if (gcashRadio.checked) {
          gcashDetails.style.display = 'block';
          codDetails.style.display = 'none';
          document.getElementById('payment_details').required = true;
        } else if (codRadio.checked) {
          gcashDetails.style.display = 'none';
          codDetails.style.display = 'block';
          document.getElementById('payment_details').required = false;
        } else {
          gcashDetails.style.display = 'none';
          codDetails.style.display = 'none';
          document.getElementById('payment_details').required = false;
        }
      }
      
      gcashRadio.addEventListener('change', togglePaymentDetails);
      codRadio.addEventListener('change', togglePaymentDetails);
      
      // Initialize on page load
      togglePaymentDetails();
    });
    </script>
  </body>
  </html>


