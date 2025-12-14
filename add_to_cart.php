<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
$pdo = get_user_pdo();

function get_or_create_cart(PDO $pdo, string $ownerKey): int {
  $stmt = $pdo->prepare('SELECT id FROM carts WHERE owner_key=? AND status="open" ORDER BY id DESC LIMIT 1');
  $stmt->execute([$ownerKey]);
  $id = (int)$stmt->fetchColumn();
  if ($id > 0) { return $id; }
  $ins = $pdo->prepare('INSERT INTO carts (owner_key) VALUES (?)');
  $ins->execute([$ownerKey]);
  return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: products.php');
  exit;
}

$productId = (int)($_POST['id'] ?? 0);
$qty = (float)($_POST['qty'] ?? 1);
if ($productId <= 0 || $qty <= 0) {
  header('Location: products.php?add=invalid');
  exit;
}

// Fetch product from admin database to ensure prices match what admin manages
try {
  require_once __DIR__ . '/admin_config.php';
  $adminPdo = get_admin_pdo();
  $prodStmt = $adminPdo->prepare('SELECT id, name, unit_cost, markup_pct, tax_pct FROM products WHERE id = ? AND is_active = 1');
  $prodStmt->execute([$productId]);
  $prod = $prodStmt->fetch();
  if (!$prod) {
    header('Location: products.php?add=missing');
    exit;
  }
  $productName = $prod['name'];
  $preTax = (float)$prod['unit_cost'] + ((float)$prod['unit_cost'] * ((float)$prod['markup_pct'] / 100.0));
  $tax = $preTax * ((float)$prod['tax_pct'] / 100.0);
  $unitPrice = round($preTax + $tax, 2);
} catch (Throwable $e) {
  header('Location: products.php?add=error');
  exit;
}

// Require login for adding to cart to keep orders tied to real users
if (empty($_SESSION['user_id'])) {
  header('Location: login.php?redirect=' . urlencode('products.php') . '&msg=login_required');
  exit;
}

$ownerKey = (string)$_SESSION['user_id'];
$cartId = get_or_create_cart($pdo, $ownerKey);

// upsert cart item
$existing = $pdo->prepare('SELECT id, qty FROM cart_items WHERE cart_id=? AND product_id=?');
$existing->execute([$cartId, $productId]);
$row = $existing->fetch();
if ($row) {
  $newQty = (float)$row['qty'] + $qty;
  $upd = $pdo->prepare('UPDATE cart_items SET qty=?, unit_price=? WHERE id=?');
  $upd->execute([$newQty, $unitPrice, (int)$row['id']]);
} else {
  $ins = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, product_name, qty, unit_price) VALUES (?, ?, ?, ?, ?)');
  $ins->execute([$cartId, $productId, $productName, $qty, $unitPrice]);
}

header('Location: products.php?add=ok');
exit;
?>


