<?php
// Admin Dashboard Test
echo "<h2>Testing Admin Dashboard...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    start_admin_session_once();
    
    // Test database connection
    $pdo = get_admin_pdo();
    echo "✅ Admin database connection successful!<br>";
    
    // Test basic queries
    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "📦 Total Products: " . $totalProducts . "<br>";
    
    $totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    echo "📋 Total Orders: " . $totalOrders . "<br>";
    
    echo "<hr>";
    echo "<h3>Admin Dashboard Test Complete!</h3>";
    echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
