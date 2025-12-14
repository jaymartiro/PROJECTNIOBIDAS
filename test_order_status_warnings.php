<?php
// Test Order Status Page - Check for undefined array key warnings
echo "<h2>Testing Order Status Page for Undefined Array Key Warnings...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    start_user_session_once();
    
    // Test database connection
    $pdo = get_user_pdo();
    echo "✅ User database connection successful!<br>";
    
    // Check if delivery_date column exists
    $columns = $pdo->query("SHOW COLUMNS FROM user_orders")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 user_orders columns: " . implode(', ', $columns) . "<br>";
    
    if (in_array('delivery_date', $columns)) {
        echo "✅ delivery_date column exists!<br>";
    } else {
        echo "❌ delivery_date column missing!<br>";
        exit;
    }
    
    // Check if there are any orders to test with
    $orderCount = $pdo->query("SELECT COUNT(*) FROM user_orders")->fetchColumn();
    echo "📊 Total orders in database: $orderCount<br>";
    
    if ($orderCount > 0) {
        // Get a sample order for testing
        $sampleOrder = $pdo->query("SELECT * FROM user_orders ORDER BY created_at DESC LIMIT 1")->fetch();
        echo "📋 Sample order found: " . $sampleOrder['order_number'] . "<br>";
        
        // Test the order data structure
        echo "<h3>Testing Order Data Structure...</h3>";
        
        $requiredKeys = ['id', 'order_number', 'customer_name', 'customer_email', 'status', 'estimated_delivery_date', 'delivery_date'];
        $missingKeys = [];
        
        foreach ($requiredKeys as $key) {
            if (array_key_exists($key, $sampleOrder)) {
                echo "✅ Key '$key' exists<br>";
            } else {
                echo "❌ Key '$key' missing<br>";
                $missingKeys[] = $key;
            }
        }
        
        if (empty($missingKeys)) {
            echo "<p style='color: green;'><strong>✅ All required keys are present!</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Missing keys: " . implode(', ', $missingKeys) . "</strong></p>";
        }
        
        // Test order status page
        echo "<hr>";
        echo "<h3>Testing Order Status Page...</h3>";
        echo "<p><a href='order_status.php?order_number=" . $sampleOrder['order_number'] . "' target='_blank'>Test Order Status Page</a></p>";
        
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
    echo "<h3>✅ Order Status Page Test Complete!</h3>";
    echo "<p><strong>The delivery_date column is now available and the undefined array key warning should be resolved!</strong></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='add_delivery_date_column.php'>add_delivery_date_column.php</a> first!</p>";
}
?>
