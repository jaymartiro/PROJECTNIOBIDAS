<?php
// Products Page Test
echo "<h2>Testing Products Page...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    start_user_session_once();
    
    // Test database connection
    $pdo = get_user_pdo();
    echo "✅ User database connection successful!<br>";
    
    // Test if required tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 User database tables: " . implode(', ', $tables) . "<br>";
    
    $requiredTables = ['users', 'carts', 'cart_items', 'user_orders', 'user_order_status_history'];
    $missingTables = array_diff($requiredTables, $tables);
    
    if (empty($missingTables)) {
        echo "✅ All required user tables are present!<br>";
    } else {
        echo "⚠️ Missing tables: " . implode(', ', $missingTables) . "<br>";
    }
    
    echo "<hr>";
    echo "<h3>Products Page Test Complete!</h3>";
    echo "<p><strong>Note:</strong> Products page now uses sample data instead of database queries.</p>";
    echo "<p><a href='products.php'>Test Products Page</a></p>";
    echo "<p><a href='add_to_cart.php?id=1&qty=1'>Test Add to Cart</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
