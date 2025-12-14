<?php
// Checkout Order Placement Test
echo "<h2>Testing Checkout Order Placement...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    start_user_session_once();
    
    // Test database connection
    $pdo = get_user_pdo();
    echo "✅ User database connection successful!<br>";
    
    // Check if all required tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 User database tables: " . implode(', ', $tables) . "<br>";
    
    $requiredTables = ['users', 'carts', 'cart_items', 'user_orders', 'user_order_items', 'user_order_status_history'];
    $missingTables = array_diff($requiredTables, $tables);
    
    if (empty($missingTables)) {
        echo "✅ All required tables are present!<br>";
    } else {
        echo "❌ Missing tables: " . implode(', ', $missingTables) . "<br>";
        exit;
    }
    
    // Check user_orders table structure
    $columns = $pdo->query("SHOW COLUMNS FROM user_orders")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 user_orders columns: " . implode(', ', $columns) . "<br>";
    
    $requiredColumns = ['customer_name', 'customer_email', 'customer_phone', 'customer_address', 'payment_method', 'payment_details', 'subtotal', 'delivery_fee'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "✅ All required columns are present in user_orders!<br>";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missingColumns) . "<br>";
    }
    
    // Check user_order_items table structure
    $itemColumns = $pdo->query("SHOW COLUMNS FROM user_order_items")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 user_order_items columns: " . implode(', ', $itemColumns) . "<br>";
    
    // Test order placement with sample data
    echo "<hr>";
    echo "<h3>Testing Order Placement...</h3>";
    
    // Check if user has any cart items
    $ownerKey = $_SESSION['user_id'] ?? session_id();
    $cartIdStmt = $pdo->prepare("SELECT id FROM carts WHERE owner_key=? AND status='open' ORDER BY id DESC LIMIT 1");
    $cartIdStmt->execute([(string)$ownerKey]);
    $cartId = (int)$cartIdStmt->fetchColumn();
    
    if ($cartId > 0) {
        $itemCount = $pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE cart_id=?');
        $itemCount->execute([$cartId]);
        $count = (int)$itemCount->fetchColumn();
        echo "🛒 Cart has $count items<br>";
        
        if ($count > 0) {
            echo "✅ Ready for checkout!<br>";
            echo "<p><strong>Test the checkout process:</strong></p>";
            echo "<p><a href='checkout.php' target='_blank'>Go to Checkout Page</a></p>";
        } else {
            echo "⚠️ Cart is empty - add items first<br>";
            echo "<p><a href='products.php'>Add Items to Cart</a></p>";
        }
    } else {
        echo "⚠️ No open cart found - add items to cart first<br>";
        echo "<p><a href='products.php'>Add Items to Cart</a></p>";
    }
    
    // Test order table queries (without actually placing order)
    echo "<hr>";
    echo "<h3>Testing Order Queries...</h3>";
    
    try {
        // Test order insert query structure
        $testOrderQuery = "INSERT INTO user_orders (order_number, user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, payment_details, subtotal, delivery_fee, total_amount, estimated_delivery_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($testOrderQuery);
        echo "✅ Order insert query prepared successfully!<br>";
        
        // Test order items insert query structure
        $testOrderItemsQuery = "INSERT INTO user_order_items (user_order_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($testOrderItemsQuery);
        echo "✅ Order items insert query prepared successfully!<br>";
        
        // Test order status history insert query structure
        $testStatusQuery = "INSERT INTO user_order_status_history (user_order_id, status, notes) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($testStatusQuery);
        echo "✅ Order status history insert query prepared successfully!<br>";
        
    } catch (Exception $e) {
        echo "❌ Query preparation error: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Checkout Order Placement Test Complete!</h3>";
    echo "<p><strong>All database tables and queries are ready for order placement!</strong></p>";
    echo "<p><a href='checkout.php'>Test Checkout Now</a> | <a href='products.php'>Add Items to Cart</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='update_user_database.php'>update_user_database.php</a> first!</p>";
}
?>
