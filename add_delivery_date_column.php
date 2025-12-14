<?php
// Add delivery_date column to user_orders table
echo "<h2>Adding delivery_date column to user_orders table...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    
    // Connect to user database
    $pdo = get_user_pdo();
    echo "✅ Connected to user database successfully!<br>";
    
    // Check if delivery_date column exists
    $columns = $pdo->query("SHOW COLUMNS FROM user_orders")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Current user_orders columns: " . implode(', ', $columns) . "<br>";
    
    if (in_array('delivery_date', $columns)) {
        echo "ℹ️ delivery_date column already exists!<br>";
    } else {
        // Add delivery_date column
        $addColumn = "ALTER TABLE user_orders ADD COLUMN delivery_date DATE NULL AFTER estimated_delivery_date";
        $pdo->exec($addColumn);
        echo "✅ Added delivery_date column to user_orders table!<br>";
        
        // Verify the column was added
        $newColumns = $pdo->query("SHOW COLUMNS FROM user_orders")->fetchAll(PDO::FETCH_COLUMN);
        echo "📋 Updated user_orders columns: " . implode(', ', $newColumns) . "<br>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Delivery Date Column Update Complete!</h3>";
    echo "<p><strong>The delivery_date column is now available for tracking actual delivery dates.</strong></p>";
    echo "<p><a href='order_status.php'>Test Order Status Page</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='update_user_database.php'>update_user_database.php</a> first!</p>";
}
?>
