<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: products.php');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: products.php?status=invalid');
  exit;
}

try {
  $pdo = get_admin_pdo();
  $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
  $stmt->execute([$id]);
  header('Location: products.php?status=deleted');
  exit;
} catch (Throwable $e) {
  header('Location: products.php?status=error');
  exit;
}
?>


