<?php
// Cart Functionality Test
echo "<h2>Testing Cart Functionality...</h2>";

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
    
    // Test cart_items table structure
    $columns = $pdo->query("SHOW COLUMNS FROM cart_items")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 cart_items columns: " . implode(', ', $columns) . "<br>";
    
    if (in_array('product_name', $columns)) {
        echo "✅ product_name column exists in cart_items!<br>";
    } else {
        echo "❌ product_name column missing from cart_items!<br>";
    }
    
    echo "<hr>";
    echo "<h3>Cart Functionality Test Complete!</h3>";
    echo "<p><strong>Note:</strong> Cart now uses sample product data instead of database queries.</p>";
    echo "<p><a href='cart.php'>Test Cart Page</a></p>";
    echo "<p><a href='add_to_cart.php?id=1&qty=1'>Test Add to Cart</a></p>";
    echo "<p><a href='products.php'>Test Products Page</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
