<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
$pdo = get_user_pdo();

$ownerKey = $_SESSION['user_id'] ?? session_id();

function get_open_cart_id(PDO $pdo, string $ownerKey): int {
  $stmt = $pdo->prepare("SELECT id FROM carts WHERE owner_key=? AND status='open' ORDER BY id DESC LIMIT 1");
  $stmt->execute([$ownerKey]);
  $id = (int)$stmt->fetchColumn();
  if ($id > 0) { return $id; }
  $ins = $pdo->prepare('INSERT INTO carts (owner_key) VALUES (?)');
  $ins->execute([$ownerKey]);
  return (int)$pdo->lastInsertId();
}

$cartId = get_open_cart_id($pdo, (string)$ownerKey);

// Handle actions: remove only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action']) && $_POST['action'] === 'remove' && !empty($_POST['item_id'])) {
    $del = $pdo->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?');
    $del->execute([(int)$_POST['item_id'], $cartId]);
  }
}

// Sample products data for cart display
$sampleProducts = [
  1 => ['id' => 1, 'name' => 'Pork Longanisa', 'image_path' => 'images/Pork Longanisa.jpg'],
  2 => ['id' => 2, 'name' => 'Longanisa Hamonada', 'image_path' => 'images/Longanisa Hamonada.jpg'],
  3 => ['id' => 3, 'name' => 'Pork Tocino', 'image_path' => 'images/Pork Tocino.jpg'],
  4 => ['id' => 4, 'name' => 'Chicken Hotdog', 'image_path' => 'images/Chicken Hotdog.jpg'],
  5 => ['id' => 5, 'name' => 'Chicken Cheese Dog', 'image_path' => 'images/Chicken Cheese dog.jpg']
];

// Get cart items without product join
$items = $pdo->prepare('SELECT ci.id as ci_id, ci.product_id, ci.qty, ci.unit_price, ci.product_name FROM cart_items ci WHERE ci.cart_id=? ORDER BY ci.id DESC');
$items->execute([$cartId]);
$cartItems = $items->fetchAll();

// Enhance cart items with product data
foreach ($cartItems as &$item) {
  $productId = (int)$item['product_id'];
  if (isset($sampleProducts[$productId])) {
    $item['name'] = $sampleProducts[$productId]['name'];
    $item['image_path'] = $sampleProducts[$productId]['image_path'];
  } else {
    // Fallback for unknown products
    $item['name'] = $item['product_name'] ?? 'Unknown Product';
    $item['image_path'] = 'images/Pork.jpg';
  }
}

$subtotal = 0.0;
foreach ($cartItems as $ci) { $subtotal += ((float)$ci['unit_price']) * ((float)$ci['qty']); }
$delivery = count($cartItems) ? 50.00 : 0.00;
$total = $subtotal + $delivery;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopping Cart - NEW CREATION FOOD INC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      .input-group .btn {
        border-radius: 0;
        width: 35px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
      }
      .input-group .btn:first-child {
        border-top-left-radius: 0.375rem;
        border-bottom-left-radius: 0.375rem;
      }
      .input-group .btn:last-child {
        border-top-right-radius: 0.375rem;
        border-bottom-right-radius: 0.375rem;
      }
      .input-group input {
        border-left: 0;
        border-right: 0;
        text-align: center;
      }
    </style>
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Your Cart</h3>
        <div class="d-flex gap-2">
          <a href="index.php" class="btn btn-outline-secondary btn-sm">Home</a>
          <a href="products.php" class="btn btn-outline-secondary btn-sm">Continue Shopping</a>
        </div>
      </div>


      <?php if (!$cartItems): ?>
        <div class="alert alert-info">Your cart is empty.</div>
      <?php else: ?>
        <div class="row g-3">
          <div class="col-12 col-lg-8">
            <?php foreach ($cartItems as $ci): ?>
              <div class="card mb-3">
                <div class="card-body d-flex gap-3 align-items-start">
                  <?php if (!empty($ci['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($ci['image_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($ci['name']); ?>" style="width:96px;height:96px;object-fit:cover;border-radius:8px">
                  <?php endif; ?>
                  <div class="flex-grow-1">
                    <div class="fw-semibold"><?php echo htmlspecialchars($ci['name']); ?></div>
                    <div class="text-muted small">₱<?php echo number_format((float)$ci['unit_price'],2); ?> each</div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                      <div class="input-group" style="width:120px">
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="changeQuantity(<?php echo (int)$ci['ci_id']; ?>, -1)">-</button>
                        <input type="number" step="0.001" min="0" id="qty_<?php echo (int)$ci['ci_id']; ?>" value="<?php echo htmlspecialchars($ci['qty']); ?>" class="form-control text-center" onchange="updateSubtotal(<?php echo (int)$ci['ci_id']; ?>, <?php echo (float)$ci['unit_price']; ?>)">
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="changeQuantity(<?php echo (int)$ci['ci_id']; ?>, 1)">+</button>
                      </div>
                      <span class="text-muted" id="subtotal_<?php echo (int)$ci['ci_id']; ?>">Subtotal: ₱<?php echo number_format(((float)$ci['unit_price'])*((float)$ci['qty']),2); ?></span>
                    </div>
                  </div>
                  <form method="post">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="item_id" value="<?php echo (int)$ci['ci_id']; ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
            <div class="col-12 col-lg-4">
              <div class="card">
                <div class="card-body order-summary">
                  <h5 class="card-title">Order Summary</h5>
                  <div class="d-flex justify-content-between"><span>Items:</span><strong class="items-total">₱<?php echo number_format($subtotal,2); ?></strong></div>
                  <div class="d-flex justify-content-between"><span>Delivery:</span><strong>₱<?php echo number_format($delivery,2); ?></strong></div>
                  <hr>
                  <div class="d-flex justify-content-between"><span>Total:</span><strong class="grand-total">₱<?php echo number_format($total,2); ?></strong></div>
                  <a class="btn btn-danger w-100 mt-3" href="checkout.php">Proceed to Checkout</a>
                </div>
              </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <script>
    function changeQuantity(itemId, change) {
      const input = document.getElementById('qty_' + itemId);
      const currentValue = parseFloat(input.value) || 0;
      const newValue = Math.max(0, currentValue + change);
      input.value = newValue.toFixed(3);
      updateSubtotal(itemId, parseFloat(input.dataset.unitPrice || 0));
    }

    function updateSubtotal(itemId, unitPrice) {
      const input = document.getElementById('qty_' + itemId);
      const quantity = parseFloat(input.value) || 0;
      const subtotal = quantity * unitPrice;
      
      // Update individual subtotal display
      const subtotalElement = document.getElementById('subtotal_' + itemId);
      if (subtotalElement) {
        subtotalElement.textContent = 'Subtotal: ₱' + subtotal.toFixed(2);
      }
      
      // Update total order summary
      updateOrderSummary();
    }

    function updateOrderSummary() {
      let totalItems = 0;
      let totalSubtotal = 0;
      
      // Calculate totals from all items
      document.querySelectorAll('input[id^="qty_"]').forEach(input => {
        const itemId = input.id.replace('qty_', '');
        const quantity = parseFloat(input.value) || 0;
        const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
        
        totalItems += quantity;
        totalSubtotal += quantity * unitPrice;
      });
      
      // Update the order summary display
      const itemsElement = document.querySelector('.order-summary .items-total');
      const subtotalElement = document.querySelector('.order-summary .subtotal-total');
      const totalElement = document.querySelector('.order-summary .grand-total');
      
      if (itemsElement) itemsElement.textContent = '₱' + totalSubtotal.toFixed(2);
      if (subtotalElement) subtotalElement.textContent = '₱' + totalSubtotal.toFixed(2);
      if (totalElement) totalElement.textContent = '₱' + (totalSubtotal + 50.00).toFixed(2);
    }

    // Initialize unit prices in data attributes
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('input[id^="qty_"]').forEach(input => {
        const itemId = input.id.replace('qty_', '');
        const subtotalElement = document.getElementById('subtotal_' + itemId);
        if (subtotalElement) {
          const currentSubtotal = parseFloat(subtotalElement.textContent.replace(/[^\d.]/g, ''));
          const currentQty = parseFloat(input.value);
          const unitPrice = currentQty > 0 ? currentSubtotal / currentQty : 0;
          input.dataset.unitPrice = unitPrice;
        }
      });
    });
    </script>
  </body>
  </html>


