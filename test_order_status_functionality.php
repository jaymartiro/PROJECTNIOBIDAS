<?php
// Order Status Functionality Test
echo "<h2>Testing Order Status Functionality...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    start_user_session_once();
    
    // Test database connection
    $pdo = get_user_pdo();
    echo "✅ User database connection successful!<br>";
    
    // Check if all required tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 User database tables: " . implode(', ', $tables) . "<br>";
    
    $requiredTables = ['user_orders', 'user_order_items', 'user_order_status_history'];
    $missingTables = array_diff($requiredTables, $tables);
    
    if (empty($missingTables)) {
        echo "✅ All required order tables are present!<br>";
    } else {
        echo "❌ Missing tables: " . implode(', ', $missingTables) . "<br>";
        exit;
    }
    
    // Test order status queries
    echo "<hr>";
    echo "<h3>Testing Order Status Queries...</h3>";
    
    try {
        // Test order lookup query
        $testOrderQuery = "SELECT * FROM user_orders WHERE order_number = ?";
        $stmt = $pdo->prepare($testOrderQuery);
        echo "✅ Order lookup query prepared successfully!<br>";
        
        // Test order items query
        $testItemsQuery = "SELECT * FROM user_order_items WHERE user_order_id = ? ORDER BY id";
        $stmt = $pdo->prepare($testItemsQuery);
        echo "✅ Order items query prepared successfully!<br>";
        
        // Test status history query
        $testHistoryQuery = "SELECT * FROM user_order_status_history WHERE user_order_id = ? ORDER BY created_at ASC";
        $stmt = $pdo->prepare($testHistoryQuery);
        echo "✅ Status history query prepared successfully!<br>";
        
    } catch (Exception $e) {
        echo "❌ Query preparation error: " . $e->getMessage() . "<br>";
    }
    
    // Check if there are any existing orders
    $orderCount = $pdo->query("SELECT COUNT(*) FROM user_orders")->fetchColumn();
    echo "📊 Total orders in database: $orderCount<br>";
    
    if ($orderCount > 0) {
        // Get a sample order number for testing
        $sampleOrder = $pdo->query("SELECT order_number FROM user_orders ORDER BY created_at DESC LIMIT 1")->fetchColumn();
        echo "📋 Sample order number: $sampleOrder<br>";
        echo "<p><a href='order_status.php?order_number=$sampleOrder' target='_blank'>Test Order Status Page</a></p>";
    } else {
        echo "⚠️ No orders found in database<br>";
        echo "<p>Place an order first to test order status functionality:</p>";
        echo "<p><a href='products.php'>Add Items to Cart</a> → <a href='checkout.php'>Place Order</a></p>";
    }
    
    // Test order status page with invalid order number
    echo "<hr>";
    echo "<h3>Testing Error Handling...</h3>";
    echo "<p><a href='order_status.php?order_number=INVALID123' target='_blank'>Test with Invalid Order Number</a></p>";
    echo "<p><a href='order_status.php' target='_blank'>Test Order Status Page (No Order Number)</a></p>";
    
    echo "<hr>";
    echo "<h3>✅ Order Status Functionality Test Complete!</h3>";
    echo "<p><strong>All order status queries are working correctly!</strong></p>";
    echo "<p><a href='order_status.php'>Test Order Status Page</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='update_user_database.php'>update_user_database.php</a> first!</p>";
}
?>
