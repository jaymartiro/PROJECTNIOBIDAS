<?php
require_once __DIR__ . '/admin_config.php';
start_admin_session_once();

echo "<h3>Before Logout:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "<br>";
echo "Admin Name: " . ($_SESSION['admin_name'] ?? 'NOT SET') . "<br>";
echo "Is Logged In: " . (is_admin_logged_in() ? 'YES' : 'NO') . "<br>";

echo "<hr>";

clear_admin_session();

echo "<h3>After Logout:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "<br>";
echo "Admin Name: " . ($_SESSION['admin_name'] ?? 'NOT SET') . "<br>";
echo "Is Logged In: " . (is_admin_logged_in() ? 'YES' : 'NO') . "<br>";

echo "<hr>";
echo "<a href='admin_login.php'>Go to Admin Login</a>";
?>
