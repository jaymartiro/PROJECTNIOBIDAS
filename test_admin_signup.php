<?php
// Admin Signup Test
echo "<h2>Testing Admin Signup...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    start_admin_session_once();
    
    // Test database connection
    $pdo = get_admin_pdo();
    echo "✅ Admin database connection successful!<br>";
    
    // Test if admin_users table exists
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Admin tables: " . implode(', ', $tables) . "<br>";
    
    if (in_array('admin_users', $tables)) {
        echo "✅ admin_users table exists!<br>";
        
        // Test if we can query admin_users table
        $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        echo "👥 Total admin users: " . $count . "<br>";
    } else {
        echo "❌ admin_users table not found!<br>";
    }
    
    echo "<hr>";
    echo "<h3>Admin Signup Test Complete!</h3>";
    echo "<p><a href='admin_signup.php'>Go to Admin Signup</a></p>";
    echo "<p><a href='admin_login.php'>Go to Admin Login</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
