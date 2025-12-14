<?php
// Admin System Configuration
// Separate database and session management for admin system

// Database configuration for admin system
define('ADMIN_DB_HOST', 'localhost');
define('ADMIN_DB_NAME', 'obidas_admin');
define('ADMIN_DB_USER', 'root');
define('ADMIN_DB_PASS', '');

// Admin session configuration
define('ADMIN_SESSION_NAME', 'OBIDAS_ADMIN_SESSION');
define('ADMIN_SESSION_LIFETIME', 3600); // 1 hour

// Start admin session with separate session name
function start_admin_session_once() {
  if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime before starting session
    ini_set('session.gc_maxlifetime', ADMIN_SESSION_LIFETIME);
    
    // Use separate session name for admin
    session_name(ADMIN_SESSION_NAME);
    session_start();
    
    // Regenerate session ID for security
    if (!isset($_SESSION['admin_session_started'])) {
      session_regenerate_id(true);
      $_SESSION['admin_session_started'] = true;
    }
  }
}

// Get admin database connection
function get_admin_pdo() {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $dsn = 'mysql:host=' . ADMIN_DB_HOST . ';dbname=' . ADMIN_DB_NAME . ';charset=utf8mb4';
      $pdo = new PDO($dsn, ADMIN_DB_USER, ADMIN_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    } catch (PDOException $e) {
      throw new Exception('Admin database connection failed: ' . $e->getMessage());
    }
  }
  return $pdo;
}

// Check if admin is logged in
function is_admin_logged_in() {
  return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_name']);
}

// Clear admin session
function clear_admin_session() {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}

// Redirect to admin login if not logged in
function require_admin_login() {
  if (!is_admin_logged_in()) {
    header('Location: admin_login.php');
    exit;
  }
}
?>
