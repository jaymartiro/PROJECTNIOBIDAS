<?php
// Checkout Functionality Test
echo "<h2>Testing Checkout Functionality...</h2>";

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
    
    // Test if user has any cart items
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
        } else {
            echo "⚠️ Cart is empty - add items first<br>";
        }
    } else {
        echo "⚠️ No open cart found - add items to cart first<br>";
    }
    
    echo "<hr>";
    echo "<h3>Checkout Functionality Test Complete!</h3>";
    echo "<p><strong>Note:</strong> Checkout now uses sample product data instead of database queries.</p>";
    echo "<p><a href='checkout.php'>Test Checkout Page</a></p>";
    echo "<p><a href='cart.php'>Test Cart Page</a></p>";
    echo "<p><a href='products.php'>Add Items to Cart</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
