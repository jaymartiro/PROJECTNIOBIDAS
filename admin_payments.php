<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

$pdo = get_admin_pdo();

// Approve/Reject actions reuse admin_orders.php handling by posting there

// Fetch GCash orders
$statusFilter = $_GET['status'] ?? '';
$where = "WHERE payment_method='gcash'";
$params = [];
if (in_array($statusFilter, ['pending','confirmed','preparing','shipped','delivered','cancelled'])) {
  $where .= " AND status=?"; $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count FROM orders o {$where} ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Payments</h3>
        <div class="d-flex gap-2">
          <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Dashboard</a>
          <a href="admin_orders.php" class="btn btn-outline-primary btn-sm">Orders</a>
          <a href="admin_logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
              <label for="status" class="form-label">Status</label>
              <select id="status" name="status" class="form-select">
                <option value="">All</option>
                <?php foreach(['pending','confirmed','preparing','shipped','delivered','cancelled'] as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo $statusFilter===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-auto">
              <button class="btn btn-primary">Filter</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Amount</th>
                  <th>Receipt</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <?php $pd = $o['payment_details'] ? json_decode($o['payment_details'], true) : null; ?>
                  <?php $hasReceipt = is_array($pd) && !empty($pd['receipt']); ?>
                  <?php $amountPaid = is_array($pd) && isset($pd['amount']) ? (float)$pd['amount'] : 0.0; ?>
                  <?php $reference = is_array($pd) && isset($pd['reference']) ? $pd['reference'] : ''; ?>
                  <?php $isPaid = $hasReceipt && abs($amountPaid - (float)$o['total_amount']) < 0.01; ?>
                  <?php 
                    // Additional validation flags
                    $hasValidReference = !empty($reference) && preg_match('/^[A-Za-z0-9]{8,20}$/', $reference);
                    $isExactAmount = $amountPaid == (float)$o['total_amount']; // Exact match required
                    $isSuspicious = in_array(strtolower($reference), ['12345678', '00000000', '11111111', 'test1234', 'fake1234']);
                  ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong></td>
                    <td>
                      <div><?php echo htmlspecialchars($o['customer_name']); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars($o['customer_email']); ?></small>
                    </td>
                    <td>
                      <div>₱<?php echo number_format($o['total_amount'],2); ?></div>
                      <?php if ($amountPaid > 0): ?>
                        <small class="text-muted">Paid: ₱<?php echo number_format($amountPaid,2); ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($hasReceipt): ?>
                        <a href="<?php echo htmlspecialchars($pd['receipt']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">View</a>
                        <?php if ($reference): ?>
                          <div class="small text-muted mt-1">Ref: <?php echo htmlspecialchars($reference); ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge bg-secondary">None</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($isSuspicious): ?>
                        <span class="badge bg-danger">SUSPICIOUS</span>
                      <?php elseif (!$hasValidReference): ?>
                        <span class="badge bg-warning">Invalid Ref</span>
                      <?php elseif (!$isExactAmount): ?>
                        <span class="badge bg-warning">Wrong Amount</span>
                      <?php elseif ($isPaid): ?>
                        <span class="badge bg-success">Verified</span>
                      <?php elseif ($hasReceipt): ?>
                        <span class="badge bg-warning">Needs Review</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">No Receipt</span>
                      <?php endif; ?>
                      
                      <span class="badge bg-<?php echo match($o['status']){ 'pending'=>'warning','confirmed'=>'info','preparing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger', default=>'secondary'}; ?>">
                        <?php echo ucfirst($o['status']); ?>
                      </span>
                    </td>
                    <td>
                      <div><?php echo date('M j, Y', strtotime($o['created_at'])); ?></div>
                      <small class="text-muted"><?php echo date('g:i A', strtotime($o['created_at'])); ?></small>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewOrder(<?php echo (int)$o['id']; ?>)"><i class="bi bi-eye"></i></button>
                        <?php if ($o['status']==='pending'): ?>
                          <button class="btn btn-outline-success" onclick="approve(<?php echo (int)$o['id']; ?>)">Approve</button>
                          <button class="btn btn-outline-danger" onclick="reject(<?php echo (int)$o['id']; ?>)">Reject</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Order Modal -->
      <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="orderDetails"></div>
          </div>
        </div>
      </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function viewOrder(id){
        fetch('admin_order_details.php?id='+id).then(r=>r.text()).then(html=>{document.getElementById('orderDetails').innerHTML=html; new bootstrap.Modal(document.getElementById('orderModal')).show();});
      }
      function approve(id){ postStatus(id,'confirm','Payment verified via GCash.'); }
      function reject(id){ postStatus(id,'cancel','Payment rejected.'); }
      function postStatus(id,action,notes){
        const form=document.createElement('form'); form.method='POST'; form.action='admin_orders.php';
        const f1=document.createElement('input'); f1.type='hidden'; f1.name='order_id'; f1.value=id; form.appendChild(f1);
        const f2=document.createElement('input'); f2.type='hidden'; f2.name='action'; f2.value=action; form.appendChild(f2);
        const f3=document.createElement('input'); f3.type='hidden'; f3.name='notes'; f3.value=notes; form.appendChild(f3);
        document.body.appendChild(form); form.submit();
      }
    </script>
  </body>
</html>


