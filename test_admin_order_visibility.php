<?php
// Test Admin Order Visibility
echo "<h2>Testing Admin Order Visibility...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    
    // Connect to admin database
    $pdo = get_admin_pdo();
    echo "✅ Connected to admin database successfully!<br>";
    
    // Check order count
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    echo "📊 Total orders in admin database: $orderCount<br>";
    
    if ($orderCount > 0) {
        // Get order details
        $orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
        echo "<h3>📋 Orders in Admin Database:</h3>";
        
        foreach ($orders as $order) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Order #:</strong> {$order['order_number']}<br>";
            echo "<strong>Customer:</strong> {$order['customer_name']} ({$order['customer_email']})<br>";
            echo "<strong>Status:</strong> {$order['status']}<br>";
            echo "<strong>Total:</strong> ₱{$order['total_amount']}<br>";
            echo "<strong>Payment:</strong> {$order['payment_method']}<br>";
            echo "<strong>Date:</strong> {$order['created_at']}<br>";
            echo "</div>";
        }
        
        // Check order items
        $orderItemsCount = $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
        echo "<p><strong>Order Items:</strong> $orderItemsCount items</p>";
        
        // Check status history
        $statusHistoryCount = $pdo->query("SELECT COUNT(*) FROM order_status_history")->fetchColumn();
        echo "<p><strong>Status History:</strong> $statusHistoryCount entries</p>";
        
        echo "<hr>";
        echo "<h3>✅ Admin Order Visibility Test Complete!</h3>";
        echo "<p style='color: green;'><strong>✅ Admin can now see user orders!</strong></p>";
        echo "<p><a href='admin_orders.php' target='_blank'>View Admin Orders Page</a></p>";
        
    } else {
        echo "⚠️ No orders found in admin database<br>";
        echo "<p>Run <a href='sync_user_orders_to_admin.php'>sync_user_orders_to_admin.php</a> to sync orders</p>";
    }
    
    // Test order status counters
    echo "<hr>";
    echo "<h3>Testing Order Status Counters...</h3>";
    
    $statusCounts = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
        'confirmed' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'")->fetchColumn(),
        'preparing' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'preparing'")->fetchColumn(),
        'shipped' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn(),
        'delivered' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn(),
        'cancelled' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn()
    ];
    
    foreach ($statusCounts as $status => $count) {
        echo "<p><strong>$status:</strong> $count orders</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure the admin database is set up correctly!</p>";
}
?>
