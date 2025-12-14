<?php
// Database Test Script
echo "<h2>Testing Database Connections...</h2>";

// Test Admin Database
echo "<h3>Testing Admin Database (obidas_admin)...</h3>";
try {
    require_once __DIR__ . '/admin_config.php';
    $adminPdo = get_admin_pdo();
    echo "✅ Admin database connection successful!<br>";
    
    // Test if tables exist
    $tables = $adminPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Admin tables: " . implode(', ', $tables) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Admin database error: " . $e->getMessage() . "<br>";
}

// Test User Database
echo "<h3>Testing User Database (obidas_user)...</h3>";
try {
    require_once __DIR__ . '/user_config.php';
    $userPdo = get_user_pdo();
    echo "✅ User database connection successful!<br>";
    
    // Test if tables exist
    $tables = $userPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 User tables: " . implode(', ', $tables) . "<br>";
    
} catch (Exception $e) {
    echo "❌ User database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li><a href='index.php'>Test Homepage</a></li>";
echo "<li><a href='admin_login.php'>Test Admin Login</a></li>";
echo "<li><a href='login.php'>Test User Login</a></li>";
echo "</ul>";
?>
