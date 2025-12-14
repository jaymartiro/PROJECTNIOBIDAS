<?php
require_once __DIR__ . '/user_config.php';

// Helper for local images with fallback
$asset = function(string $filename, string $fallbackUrl): string {
  $imagesPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
  if (file_exists($imagesPath)) { return 'images/' . $filename; }
  return $fallbackUrl;
};

$logoUrl = $asset('Logo NCF.jpg', 'https://i.imgur.com/3vQn3sF.png');

// Pricing helper
function compute_price(float $unitCost, float $markupPct, float $taxPct): float {
  $preTax = $unitCost + ($unitCost * ($markupPct / 100.0));
  $tax = $preTax * ($taxPct / 100.0);
  return round($preTax + $tax, 2);
}

$pdo = get_user_pdo();

// Read-only preview mode only when explicitly requested via URL
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';

// Load products from admin database so users can view items admins added
$dbProducts = [];
try {
  require_once __DIR__ . '/admin_config.php';
  $adminPdo = get_admin_pdo();
  // Get products with current stock calculation
  $stmt = $adminPdo->query("
    SELECT p.id, p.sku, p.name, p.image_path, p.unit_cost, p.markup_pct, p.tax_pct, p.is_active,
    COALESCE(SUM(CASE WHEN m.movement_type='in' THEN m.quantity ELSE -m.quantity END),0) AS current_stock
    FROM products p 
    LEFT JOIN inventory_movements m ON m.product_id = p.id 
    WHERE p.is_active = 1
    GROUP BY p.id 
    ORDER BY p.id DESC
  ");
  $dbProducts = $stmt->fetchAll();
} catch (Throwable $e) {
  // If admin DB is not reachable, fall back to empty list; UI will show fallback images
  $dbProducts = [];
}

// Cart count for current user/session
start_user_session_once();
$ownerKey = $_SESSION['user_id'] ?? session_id();
$cartIdStmt = $pdo->prepare("SELECT id FROM carts WHERE owner_key=? AND status='open' ORDER BY id DESC LIMIT 1");
$cartIdStmt->execute([(string)$ownerKey]);
$currentCartId = (int)$cartIdStmt->fetchColumn();
$cartCount = 0;
if ($currentCartId > 0) {
  $countStmt = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id=?');
  $countStmt->execute([$currentCartId]);
  $cartCount = (float)$countStmt->fetchColumn();
}

// Fallback images if there are no DB products yet
$fallbackImages = [
  $asset('Pork Longanisa.jpg', 'https://images.unsplash.com/photo-1615937691196-94fcb3b8a1a1?q=80&w=1200&auto=format&fit=crop'),
  $asset('Pork.jpg', 'https://images.unsplash.com/photo-1544025164-76bc3997d9ea?q=80&w=1200&auto=format&fit=crop'),
  $asset('Pork Tocino.jpg', 'https://images.unsplash.com/photo-1604908554007-3f3e1d1c2a42?q=80&w=1200&auto=format&fit=crop'),
  $asset('Longanisa Hamonada.jpg', 'https://images.unsplash.com/photo-1615937691196-94fcb3b8a1a1?q=80&w=1200&auto=format&fit=crop'),
  $asset('Pork.jpg', 'https://images.unsplash.com/photo-1544025164-76bc3997d9ea?q=80&w=1200&auto=format&fit=crop'),
  $asset('Pork Tocino.jpg', 'https://images.unsplash.com/photo-1604908554007-3f3e1d1c2a42?q=80&w=1200&auto=format&fit=crop'),
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products - NEW CREATION FOOD INC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body{ background:#f4f4f5; }
      .brand-badge { display:flex; align-items:center; gap:.5rem; font-weight:800; text-transform:uppercase; }
      .brand-badge img{ width:44px; height:44px; object-fit:contain; }
      .product-card{ border:0; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.06); transition:transform .2s ease, box-shadow .2s ease; background:#fff; }
      .product-card:hover{ transform:translateY(-3px); box-shadow:0 10px 24px rgba(0,0,0,.12); }
      .product-card img{ width:100%; height:220px; object-fit:cover; display:block; }
      .toolbar { display:flex; align-items:center; gap:10px; padding:10px 0 0 0; }
      .back-link { color:#111827; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
      .back-link:hover{ text-decoration:underline; }
    </style>
  </head>
  <body>
    <div class="container py-3 py-md-4">
      <nav class="navbar navbar-expand-lg bg-white rounded-3 px-3 py-2 mb-3">
        <div class="container-fluid px-0">
          <div class="brand-badge">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="Logo" onerror="this.style.display='none'">
            <div>
              <div style="font-size:14px; color:#6b7280; line-height:1">NEW CREATION</div>
              <div style="font-size:16px; color:#111827; line-height:1">FOOD INC</div>
            </div>
          </div>
          <div class="d-flex gap-2 ms-auto">
            <?php if ($isPreview): ?>
              <a class="btn btn-primary btn-sm px-3" href="admin_dashboard.php">Go back to dashboard</a>
            <?php else: ?>
              <?php
                require_once __DIR__ . '/user_config.php';
                start_user_session_once();
                $isLogged = !empty($_SESSION['user_id']);
              ?>
              <?php if ($isLogged): ?>
                <div class="dropdown">
                  <button class="btn btn-light border rounded-pill d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-md-inline">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>!</span>
                    <img src="<?php echo isset($_SESSION['avatar_path']) ? htmlspecialchars($_SESSION['avatar_path']) : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'U'); ?>" alt="avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
                    <span class="ms-1">▾</span>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="products.php">Products</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="user_logout.php">Logout</a></li>
                  </ul>
                </div>
              <?php else: ?>
                <a class="btn btn-outline-dark btn-sm px-3" href="login.php">Log in</a>
                <a class="btn btn-primary btn-sm px-3" href="signup.php">Sign up</a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </nav>

      <div class="toolbar d-flex justify-content-between align-items-center">
        <?php if (!$isPreview): ?>
          <a class="back-link" href="index.php">&#8592; Back</a>
        <?php endif; ?>
        <div class="d-flex gap-2">
          <?php if (!$isPreview): ?>
            <a href="index.php" class="btn btn-secondary btn-sm">Home</a>
          <?php endif; ?>
          <?php if ($isPreview): ?>
            <a href="admin_product_edit.php" class="btn btn-warning btn-sm">Edit Prices & Stock</a>
          <?php endif; ?>
          <?php if (!$isPreview): ?>
            <a href="cart.php" class="btn btn-danger btn-sm">
              <span style="margin-right:6px;" aria-hidden="true">🛒</span>
              Cart (<?php echo ($cartCount == (int)$cartCount ? (int)$cartCount : number_format($cartCount, 3)); ?>)
            </a>
          <?php else: ?>
            <span class="badge text-bg-warning align-self-center">Read-only (Preview)</span>
          <?php endif; ?>
        </div>
      </div>

       <section class="mt-2">
         <div class="row g-3 g-md-4">
           <?php if ($dbProducts): ?>
             <?php foreach ($dbProducts as $p): $price = compute_price((float)$p['unit_cost'], (float)$p['markup_pct'], (float)$p['tax_pct']); $stock = (float)$p['current_stock']; ?>
               <div class="col-12 col-md-6 col-lg-4">
                 <div class="product-card">
                   <img src="<?php echo htmlspecialchars($p['image_path'] ?: $asset('Pork.jpg', 'https://images.unsplash.com/photo-1544025164-76bc3997d9ea?q=80&w=1200&auto=format&fit=crop'), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                   <div class="p-2">
                     <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
                     <div class="d-flex justify-content-between small text-muted">
                       <span>Stock: <?php echo number_format($stock, 3); ?></span>
                       <span>Price: ₱<?php echo number_format($price, 2); ?></span>
                     </div>
                      <?php if (!$isPreview): ?>
                        <form action="add_to_cart.php" method="post" class="mt-2 d-flex justify-content-between align-items-center gap-2">
                          <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                          <input type="number" name="qty" min="1" value="1" class="form-control form-control-sm" style="width:80px">
                          <button type="submit" class="btn btn-danger btn-sm">Add to Cart</button>
                        </form>
                      <?php else: ?>
                        <div class="mt-2 text-muted small">Admin preview - ordering disabled</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
             <?php endforeach; ?>
           <?php else: ?>
             <?php foreach ($fallbackImages as $src): ?>
               <div class="col-12 col-md-6 col-lg-4">
                 <div class="product-card">
                   <img src="<?php echo htmlspecialchars($src, ENT_QUOTES); ?>" alt="Product">
                 </div>
               </div>
             <?php endforeach; ?>
           <?php endif; ?>
         </div>
       </section>

    </div>
  </body>
  </html>


