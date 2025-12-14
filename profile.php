<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// Ensure this is a regular user, not an admin
if (!empty($_SESSION['admin_id'])) {
  header('Location: admin_dashboard.php');
  exit;
}

// Double check that we have a valid user session
if (empty($_SESSION['user_id']) || empty($_SESSION['user_name'])) {
  header('Location: login.php');
  exit;
}

$pdo = get_user_pdo();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $avatar_path = null;

  // Validate required fields
  if (empty($name)) {
    $errors[] = 'Name is required.';
  }

  // Handle avatar upload
  if (!empty($_FILES['avatar']['name'])) {
    $upload = $_FILES['avatar'];
    if ($upload['error'] === UPLOAD_ERR_OK) {
      $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/jpg'=>'jpg'];
      $max_size = 5 * 1024 * 1024; // 5MB
      
      // Check file size
      if ($upload['size'] > $max_size) {
        $errors[] = 'File size must be less than 5MB.';
      } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $upload['tmp_name']);
        finfo_close($finfo);
        
        if (isset($allowed[$mime])) {
          $ext = $allowed[$mime];
          $base = preg_replace('/[^A-Za-z0-9_-]/','_', pathinfo($upload['name'], PATHINFO_FILENAME));
          $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
          if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
          $rel = 'uploads/avatars/' . $base . '_' . uniqid() . '.' . $ext;
          $abs = __DIR__ . DIRECTORY_SEPARATOR . $rel;
          
          if (move_uploaded_file($upload['tmp_name'], $abs)) { 
            $avatar_path = $rel; 
          } else {
            $errors[] = 'Failed to upload image.';
          }
        } else {
          $errors[] = 'Please select a valid image file (JPG, PNG, or WEBP).';
        }
      }
    } else {
      $errors[] = 'Error uploading file.';
    }
  }

  if (empty($errors)) {
    try {
      if ($avatar_path) {
        // Delete old avatar if exists
        $old_avatar = $pdo->prepare('SELECT avatar_path FROM users WHERE id=?');
        $old_avatar->execute([$userId]);
        $old_path = $old_avatar->fetchColumn();
        if ($old_path && file_exists($old_path)) {
          unlink($old_path);
        }
        
        $stmt = $pdo->prepare('UPDATE users SET name=?, phone=?, address=?, avatar_path=? WHERE id=?');
        $stmt->execute([$name, $phone, $address, $avatar_path, $userId]);
        $_SESSION['avatar_path'] = $avatar_path;
      } else {
        $stmt = $pdo->prepare('UPDATE users SET name=?, phone=?, address=? WHERE id=?');
        $stmt->execute([$name, $phone, $address, $userId]);
      }
      $_SESSION['user_name'] = $name;
      $saved = true;
    } catch (Throwable $e) {
      $errors[] = 'Database error: ' . $e->getMessage();
    }
  }
}

$user = $pdo->prepare('SELECT name, email, phone, address, avatar_path FROM users WHERE id=?');
$user->execute([$userId]);
$u = $user->fetch();

// No need to check for admin since users and admins are in separate systems

// Ensure we have user data
if (!$u) {
  header('Location: login.php');
  exit;
}

// Get user's orders (from user_orders in user DB)
$ordersStmt = $pdo->prepare('SELECT * FROM user_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$ordersStmt->execute([$userId]);
$userOrders = $ordersStmt->fetchAll();

// Get recent order status updates for notifications
$recentUpdatesStmt = $pdo->prepare('
  SELECT o.order_number, osh.status, osh.notes, osh.created_at 
  FROM user_order_status_history osh 
  JOIN user_orders o ON o.id = osh.user_order_id 
  WHERE o.user_id = ? AND osh.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  ORDER BY osh.created_at DESC 
  LIMIT 5
');
$recentUpdatesStmt->execute([$userId]);
$recentUpdates = $recentUpdatesStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
      .profile-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
      .profile-header { text-align: center; margin-bottom: 3rem; }
      .profile-title { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin-bottom: 0.5rem; }
      .profile-subtitle { color: #6c757d; font-size: 1.1rem; }
      .profile-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; border: 1px solid #e9ecef; }
      .profile-card-header { padding: 1.5rem 2rem 1rem; border-bottom: 1px solid #e9ecef; }
      .profile-card-title { font-size: 1.25rem; font-weight: 600; color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
      .profile-card-body { padding: 2rem; }
      .profile-avatar { 
        width: 120px; 
        height: 120px; 
        border-radius: 50%; 
        object-fit: cover; 
        border: 3px solid #dc3545; 
        display: block; 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }
      .profile-photo-container { 
        position: relative; 
        display: inline-block; 
        margin-bottom: 1.5rem;
      }
      .change-photo-btn { 
        position: absolute; 
        bottom: 12px; 
        left: 50%; 
        transform: translateX(-50%); 
        background-color: rgba(0, 0, 0, 0.5); 
        border: none; 
        color: white; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.65rem; 
        font-weight: 400; 
        box-shadow: 0 1px 4px rgba(0,0,0,0.4);
        transition: all 0.3s ease;
        z-index: 10;
        white-space: nowrap;
        min-width: 60px;
        backdrop-filter: blur(2px);
        border: 1px solid rgba(255,255,255,0.05);
        animation: float 4s ease-in-out infinite;
        opacity: 0.8;
      }
      
      @keyframes float {
        0%, 100% { transform: translateX(-50%) translateY(0px); }
        50% { transform: translateX(-50%) translateY(-0.5px); }
      }
      .change-photo-btn:hover { 
        background-color: rgba(0, 0, 0, 0.6); 
        transform: translateX(-50%) translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.5);
        backdrop-filter: blur(3px);
        opacity: 1;
      }
      .change-photo-btn:active {
        transform: translateX(-50%) translateY(0px);
        box-shadow: 0 1px 2px rgba(0,0,0,0.3);
      }
      .change-photo-btn i { margin-right: 5px; }
      .form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
      .form-control { border: 1px solid #ced4da; border-radius: 8px; padding: 0.75rem; font-size: 1rem; }
      .form-control:focus { border-color: #dc3545; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25); }
      .btn-primary { background-color: #dc3545; border-color: #dc3545; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; }
      .btn-primary:hover { background-color: #c82333; border-color: #bd2130; }
      .btn-outline-secondary { border-color: #6c757d; color: #6c757d; padding: 0.5rem 1.5rem; border-radius: 8px; }
      .btn-outline-secondary:hover { background-color: #6c757d; border-color: #6c757d; }
      .account-info-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #f8f9fa; }
      .account-info-item:last-child { border-bottom: none; }
      .account-info-label { font-weight: 600; color: #495057; }
      .account-info-value { color: #6c757d; }
      .badge-role { background-color: #007bff; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; }
      .icon { color: #dc3545; font-size: 1.1rem; }
      .order-card { background: white; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #e9ecef; }
      .order-header { display: flex; justify-content: between; align-items: center; margin-bottom: 0.5rem; }
      .order-number { font-weight: 600; color: #2c3e50; }
      .order-date { color: #6c757d; font-size: 0.9rem; }
      .order-status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; }
      .status-pending { background-color: #fff3cd; color: #856404; }
      .status-confirmed { background-color: #d1ecf1; color: #0c5460; }
      .status-preparing { background-color: #cce5ff; color: #004085; }
      .status-shipped { background-color: #e2e3e5; color: #383d41; }
      .status-delivered { background-color: #d4edda; color: #155724; }
      .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
  </head>
  <body>
    <div class="profile-container">
      <!-- Header -->
      <div class="profile-header">
        <h1 class="profile-title">User Profile</h1>
        <p class="profile-subtitle">Manage your account information and settings</p>
        <div class="d-flex justify-content-center gap-2 mt-3">
          <a class="btn btn-outline-secondary" href="index.php">Back to Home</a>
        </div>
      </div>

      <?php if ($saved): ?><div class="alert alert-success">Profile updated successfully!</div><?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) { echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-12 col-lg-8">
          <div class="profile-card">
            <div class="profile-card-header">
              <h3 class="profile-card-title">
                <i class="bi bi-person icon"></i>
                Personal Information
              </h3>
            </div>
            <div class="profile-card-body">
              <!-- Profile Photo -->
              <div class="text-center mb-4">
                <div class="profile-photo-container position-relative d-inline-block">
                  <?php if (!empty($u['avatar_path']) && file_exists($u['avatar_path'])): ?>
                    <img src="<?php echo htmlspecialchars($u['avatar_path']); ?>" alt="Profile Photo" class="profile-avatar" id="profileImage">
                  <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['name'] ?? 'U'); ?>&size=120&background=dc3545&color=fff" alt="Profile Photo" class="profile-avatar" id="profileImage">
                  <?php endif; ?>
                  <button type="button" class="btn btn-danger change-photo-btn" onclick="changePhoto()">
                    <i class="bi bi-camera"></i> Change Photo
                  </button>
                </div>
              </div>
              
              <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">First Name *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($u['name'] ?? ''); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email Address *</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" disabled>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>" placeholder="Enter phone number">
                  </div>
                  <!-- Hidden file input for avatar -->
                  <input type="file" name="avatar" id="avatarInput" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this)" style="display: none;">
                  <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="3" placeholder="Enter your full address"><?php echo htmlspecialchars($u['address'] ?? ''); ?></textarea>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-primary" type="submit">
                      <i class="bi bi-lock"></i> Update Profile
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Account Information -->
        <div class="col-12 col-lg-4">
          <div class="profile-card">
            <div class="profile-card-header">
              <h3 class="profile-card-title">
                <i class="bi bi-info-circle icon"></i>
                Account Information
              </h3>
            </div>
            <div class="profile-card-body">
              <div class="account-info-item">
                <span class="account-info-label">Username</span>
                <span class="account-info-value"><?php echo htmlspecialchars(ucwords($u['name'] ?? '')); ?></span>
              </div>
              <div class="account-info-item">
                <span class="account-info-label">Role</span>
                <span class="badge-role">CUSTOMER</span>
              </div>
              <div class="account-info-item">
                <span class="account-info-label">Member Since</span>
                <span class="account-info-value"><?php echo date('F j, Y', strtotime($u['created_at'] ?? 'now')); ?></span>
              </div>
              <div class="account-info-item">
                <span class="account-info-label">Last Login</span>
                <span class="account-info-value"><?php echo date('F j, Y g:i A'); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <?php if (!empty($userOrders)): ?>
        <div class="profile-card">
          <div class="profile-card-header">
            <h3 class="profile-card-title">
              <i class="bi bi-bag icon"></i>
              Recent Orders
            </h3>
          </div>
          <div class="profile-card-body">
            <?php foreach ($userOrders as $order): ?>
              <div class="order-card">
                <div class="order-header">
                  <div>
                    <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                    <div class="order-date">
                      <?php echo date('M j, Y', strtotime($order['created_at'])); ?> • 
                      ₱<?php echo number_format($order['total_amount'], 2); ?>
                      <?php if ($order['estimated_delivery_date'] && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                        <br><span class="text-info"><i class="bi bi-truck"></i> Expected: <?php echo date('M j, Y', strtotime($order['estimated_delivery_date'])); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-end">
                    <span class="order-status status-<?php echo $order['status']; ?>">
                      <?php echo ucfirst($order['status']); ?>
                    </span>
                    <div class="mt-2">
                      <a href="order_status.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Recent Order Updates -->
      <?php if (!empty($recentUpdates)): ?>
        <div class="profile-card">
          <div class="profile-card-header">
            <h3 class="profile-card-title">
              <i class="bi bi-bell icon"></i>
              Recent Order Updates
            </h3>
          </div>
          <div class="profile-card-body">
            <?php foreach ($recentUpdates as $update): ?>
              <div class="d-flex align-items-center mb-3 p-3 border rounded">
                <div class="flex-shrink-0">
                  <span class="order-status status-<?php echo $update['status']; ?>">
                    <?php echo ucfirst($update['status']); ?>
                  </span>
                </div>
                <div class="flex-grow-1 ms-3">
                  <div class="fw-bold">Order #<?php echo htmlspecialchars($update['order_number']); ?></div>
                  <?php if ($update['notes']): ?>
                    <div class="text-muted small"><?php echo htmlspecialchars($update['notes']); ?></div>
                  <?php endif; ?>
                  <?php if ($update['status'] === 'confirmed'): ?>
                    <div class="text-info small mt-1">
                      <i class="bi bi-truck"></i> <strong>Delivery Date:</strong> 
                      <?php 
                        $deliveryStmt = $pdo->prepare('SELECT estimated_delivery_date FROM user_orders WHERE order_number = ?');
                        $deliveryStmt->execute([$update['order_number']]);
                        $deliveryDate = $deliveryStmt->fetchColumn();
                        if ($deliveryDate) {
                          echo date('F j, Y', strtotime($deliveryDate));
                        } else {
                          echo 'To be confirmed';
                        }
                      ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="flex-shrink-0">
                  <small class="text-muted"><?php echo date('M j, g:i A', strtotime($update['created_at'])); ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <script>
      function changePhoto() {
        console.log('Change photo button clicked');
        const fileInput = document.getElementById('avatarInput');
        console.log('File input element:', fileInput);
        if (fileInput) {
          fileInput.click();
          console.log('File input clicked');
        } else {
          console.error('File input not found');
          alert('Error: File input not found. Please refresh the page and try again.');
        }
      }

      // Test if button is working on page load
      document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded, checking change photo button...');
        const button = document.querySelector('.change-photo-btn');
        const fileInput = document.getElementById('avatarInput');
        console.log('Button found:', button);
        console.log('File input found:', fileInput);
      });

      function previewImage(input) {
        if (input.files && input.files[0]) {
          const file = input.files[0];
          const maxSize = 5 * 1024 * 1024; // 5MB
          const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
          
          // Validate file size
          if (file.size > maxSize) {
            alert('File size must be less than 5MB');
            input.value = '';
            return;
          }
          
          // Validate file type
          if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, or WEBP)');
            input.value = '';
            return;
          }
          
          // Show loading state
          const avatar = document.getElementById('profileImage');
          const originalSrc = avatar.src;
          avatar.style.opacity = '0.5';
          
          const reader = new FileReader();
          reader.onload = function(e) {
            avatar.src = e.target.result;
            avatar.style.opacity = '1';
          };
          reader.onerror = function() {
            avatar.src = originalSrc;
            avatar.style.opacity = '1';
            alert('Error loading image');
          };
          reader.readAsDataURL(file);
        }
      }

      // Form validation
      document.querySelector('form').addEventListener('submit', function(e) {
        const fileInput = document.querySelector('input[name="avatar"]');
        if (fileInput.files.length > 0) {
          const file = fileInput.files[0];
          const maxSize = 5 * 1024 * 1024; // 5MB
          const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
          
          if (file.size > maxSize) {
            e.preventDefault();
            alert('File size must be less than 5MB');
            return;
          }
          
          if (!allowedTypes.includes(file.type)) {
            e.preventDefault();
            alert('Please select a valid image file (JPG, PNG, or WEBP)');
            return;
          }
        }
      });
    </script>
  </body>
</html>


