<?php
// User System Configuration
// Separate database and session management for user system

// Database configuration for user system
define('USER_DB_HOST', 'localhost');
define('USER_DB_NAME', 'obidas_user');
define('USER_DB_USER', 'root');
define('USER_DB_PASS', '');

// User session configuration
define('USER_SESSION_NAME', 'OBIDAS_USER_SESSION');
define('USER_SESSION_LIFETIME', 7200); // 2 hours

// Start user session with separate session name
function start_user_session_once() {
  if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime before starting session
    ini_set('session.gc_maxlifetime', USER_SESSION_LIFETIME);
    
    // Use separate session name for user
    session_name(USER_SESSION_NAME);
    session_start();
    
    // Regenerate session ID for security
    if (!isset($_SESSION['user_session_started'])) {
      session_regenerate_id(true);
      $_SESSION['user_session_started'] = true;
    }
  }
}

// Get user database connection
function get_user_pdo() {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $dsn = 'mysql:host=' . USER_DB_HOST . ';dbname=' . USER_DB_NAME . ';charset=utf8mb4';
      $pdo = new PDO($dsn, USER_DB_USER, USER_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    } catch (PDOException $e) {
      throw new Exception('User database connection failed: ' . $e->getMessage());
    }
  }
  return $pdo;
}

// Check if user is logged in
function is_user_logged_in() {
  return !empty($_SESSION['user_id']) && !empty($_SESSION['user_name']);
}

// Clear user session
function clear_user_session() {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}

// Redirect to user login if not logged in
function require_user_login() {
  if (!is_user_logged_in()) {
    header('Location: login.php');
    exit;
  }
}
?>
