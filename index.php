<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();

// Asset helper: prefer ./images/<file>, then ./assets/<file>, else remote URL
$asset = function(string $filename, string $fallbackUrl): string {
  $imagesPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
  if (file_exists($imagesPath)) {
    return 'images/' . $filename;
  }
  $assetsPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $filename;
  if (file_exists($assetsPath)) {
    return 'assets/' . $filename;
  }
  return $fallbackUrl;
};

// Use your filenames under /images by default
$logoUrl = $asset('Logo NCF.jpg', 'https://i.imgur.com/3vQn3sF.png');
$heroUrl = $asset('BG.jpg', 'https://images.unsplash.com/photo-1544025162-d76694265947?q=80&w=2070&auto=format&fit=crop');

// Helpers for price calculation
function compute_price(float $unitCost, float $markupPct, float $taxPct): float {
  $preTax = $unitCost + ($unitCost * ($markupPct / 100.0));
  $tax = $preTax * ($taxPct / 100.0);
  return round($preTax + $tax, 2);
}

// Fetch products for homepage grid from admin database
$homeProducts = [];
$pendingOrdersCount = 0;

try {
  // Load products from admin database with real-time stock and pricing
  require_once __DIR__ . '/admin_config.php';
  $adminPdo = get_admin_pdo();
  
  // Get products with current stock calculation (limit to 3 for homepage)
  $stmt = $adminPdo->query("
    SELECT p.id, p.sku, p.name, p.image_path, p.unit_cost, p.markup_pct, p.tax_pct, p.is_active,
    COALESCE(SUM(CASE WHEN m.movement_type='in' THEN m.quantity ELSE -m.quantity END),0) AS current_stock
    FROM products p 
    LEFT JOIN inventory_movements m ON m.product_id = p.id 
    WHERE p.is_active = 1
    GROUP BY p.id 
    ORDER BY p.id DESC
    LIMIT 3
  ");
  $homeProducts = $stmt->fetchAll();
  
  // Get pending orders count for logged in users
  if (is_user_logged_in()) {
    $userPdo = get_user_pdo();
    $pendingStmt = $userPdo->prepare("SELECT COUNT(*) FROM user_orders WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$_SESSION['user_id']]);
    $pendingOrdersCount = (int)$pendingStmt->fetchColumn();
  }
  
} catch (Throwable $e) {
  // If admin DB is not reachable, fall back to empty list
  $homeProducts = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NEW CREATION FOOD INC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
      body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', sans-serif; background:#f4f4f5; }
      .brand-badge { display:flex; align-items:center; gap:.5rem; font-weight:800; letter-spacing:.5px; text-transform:uppercase; }
      .brand-badge img { width:44px; height:44px; object-fit:contain; }
      .hero { position:relative; border-radius:10px; overflow:hidden; background:#111; }
      .hero::before { content:""; position:absolute; inset:0; background:url('<?php echo htmlspecialchars($heroUrl, ENT_QUOTES); ?>') center/cover no-repeat; filter:brightness(.55); }
      .hero-content { position:relative; z-index:1; padding:72px 28px; color:#fff; text-align:center; }
      .hero-quote { font-size:clamp(18px, 2.6vw, 28px); font-weight:300; font-style:italic; }
      .section { background:#fff; border-radius:10px; padding:28px; }
      .product-card { border:0; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.06); transition:transform .2s ease, box-shadow .2s ease; }
      .product-card:hover { transform:translateY(-3px); box-shadow:0 10px 24px rgba(0,0,0,.12); }
      .product-card img { width:100%; height:240px; object-fit:cover; }
      .footer-top { background:#fff; border-radius:10px; padding:28px; }
      .footer-links a { color:#6b7280; text-decoration:none; display:block; padding:.2rem 0; }
      .footer-links a:hover { color:#111827; }
      .copyright { font-size:12px; color:#6b7280; }
      .btn-outline-dark { --bs-btn-color:#111827; --bs-btn-border-color:#d1d5db; --bs-btn-hover-bg:#111827; --bs-btn-hover-border-color:#111827; }
      .btn-primary { background:#111827; border-color:#111827; }
      .btn-primary:hover { background:#0b1220; border-color:#0b1220; }
    </style>
  </head>
  <body>
    <div class="container py-3 py-md-4">
      <!-- Header / Navbar -->
      <nav class="navbar navbar-expand-lg bg-white rounded-3 px-3 py-2 mb-3" aria-label="Main navigation">
        <div class="container-fluid px-0">
          <div class="brand-badge">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="NCFI Logo" onerror="this.style.display='none'">
            <div>
              <div style="font-size:14px; color:#6b7280; line-height:1">NEW CREATION</div>
              <div style="font-size:16px; color:#111827; line-height:1">FOOD INC</div>
            </div>
          </div>
          <div class="d-flex gap-2 ms-auto">
            <?php if (is_user_logged_in()): ?>
              <!-- User Navigation -->
              <div class="dropdown">
                <button class="btn btn-light border rounded-pill d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="d-none d-md-inline">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>!</span>
                  <img src="<?php echo isset($_SESSION['avatar_path']) ? htmlspecialchars($_SESSION['avatar_path']) : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'U'); ?>" alt="avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
                  <span class="ms-1">▾</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                  <li><a class="dropdown-item" href="products.php">Products</a></li>
                  <li><a class="dropdown-item" href="cart.php">Cart</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="user_logout.php">Logout</a></li>
                </ul>
              </div>
            <?php else: ?>
              <!-- Guest Navigation -->
              <a class="btn btn-outline-dark btn-sm px-3" href="login.php">Log in</a>
              <a class="btn btn-primary btn-sm px-3" href="signup.php">Sign up</a>
              <a class="btn btn-outline-danger btn-sm px-3" href="admin_login.php">Admin</a>
            <?php endif; ?>
          </div>
        </div>
      </nav>


      <!-- Hero -->
      <section class="hero mb-4">
        <div class="hero-content">
          <p class="hero-quote mb-4">"Freshness is not an option, it's our standard."</p>
        </div>
      </section>

      <!-- Products -->
      <section id="products" class="section mb-4">
        <div class="mb-3">
          <a href="products.php" class="btn btn-outline-primary btn-sm px-4">View all products</a>
        </div>
        <div class="row g-3 g-md-4">
          <?php if ($homeProducts): ?>
            <?php foreach ($homeProducts as $p): $price = compute_price((float)$p['unit_cost'], (float)$p['markup_pct'], (float)$p['tax_pct']); $stock = (float)$p['current_stock']; ?>
              <div class="col-12 col-md-4">
                <div class="card product-card">
                  <img src="<?php echo htmlspecialchars($p['image_path'] ?: $asset('Pork.jpg', 'https://images.unsplash.com/photo-1544025164-76bc3997d9ea?q=80&w=1200&auto=format&fit=crop'), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                  <div class="p-2">
                    <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="d-flex justify-content-between small text-muted">
                      <span>Stock: <?php echo number_format($stock, 3); ?></span>
                      <span>Price: ₱<?php echo number_format($price, 2); ?></span>
                    </div>
                    <form action="add_to_cart.php" method="post" class="mt-2 d-flex justify-content-between align-items-center gap-2">
                      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                      <input type="number" name="qty" min="1" value="1" class="form-control form-control-sm" style="width:80px">
                      <button type="submit" class="btn btn-danger btn-sm">Add to Cart</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12 col-md-4">
              <div class="card product-card">
                <img src="<?php echo htmlspecialchars($asset('Pork Longanisa.jpg', 'https://images.unsplash.com/photo-1615937691196-94fcb3b8a1a1?q=80&w=1200&auto=format&fit=crop'), ENT_QUOTES); ?>" alt="Product 1">
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card product-card">
                <img src="<?php echo htmlspecialchars($asset('Longanisa Hamonada.jpg', 'https://images.unsplash.com/photo-1544025164-76bc3997d9ea?q=80&w=1200&auto=format&fit=crop'), ENT_QUOTES); ?>" alt="Product 2">
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card product-card">
                <img src="<?php echo htmlspecialchars($asset('Pork Tocino.jpg', 'https://images.unsplash.com/photo-1604908554007-3f3e1d1c2a42?q=80&w=1200&auto=format&fit=crop'), ENT_QUOTES); ?>" alt="Product 3">
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Footer -->
      <footer class="footer-top mb-3">
        <div class="row g-4">
          <div class="col-12 col-md-6">
            <h6 class="mb-3" style="color:#111827">The company</h6>
            <div class="footer-links">
              <a href="#">Home</a>
              <a href="#">About us</a>
              <a href="#">Contact</a>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <h6 class="mb-3" style="color:#111827">The Application</h6>
            <div class="footer-links">
              <a href="#">Sign up</a>
              <a href="#">Log in</a>
              <a href="#">Listings</a>
              <a href="#">FAQ</a>
            </div>
          </div>
        </div>
      </footer>

      <div class="d-flex align-items-center justify-content-between py-2 px-3 bg-white rounded-3">
        <div class="d-flex align-items-center gap-2">
          <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="NCFI Logo Small" style="width:20px;height:20px" onerror="this.style.display='none'">
          <span class="copyright">NEW CREATION FOOD INC</span>
        </div>
        <span class="copyright">NEW CREATION FOOD Inc. © 2025, All Rights Reserved</span>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>


