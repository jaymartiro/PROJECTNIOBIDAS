<?php
// Sync User Orders to Admin Database
echo "<h2>Syncing User Orders to Admin Database...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    require_once __DIR__ . '/admin_config.php';
    
    // Connect to both databases
    $userPdo = get_user_pdo();
    $adminPdo = get_admin_pdo();
    echo "✅ Connected to both databases successfully!<br>";
    
    // Get all user orders that haven't been synced to admin database
    $userOrders = $userPdo->query("SELECT * FROM user_orders ORDER BY created_at ASC")->fetchAll();
    echo "📋 Found " . count($userOrders) . " user orders to sync<br>";
    
    $syncedCount = 0;
    $skippedCount = 0;
    
    foreach ($userOrders as $userOrder) {
        // Check if order already exists in admin database
        $existingOrder = $adminPdo->prepare("SELECT id FROM orders WHERE order_number = ?");
        $existingOrder->execute([$userOrder['order_number']]);
        
        if ($existingOrder->fetch()) {
            echo "ℹ️ Order {$userOrder['order_number']} already exists in admin database<br>";
            $skippedCount++;
            continue;
        }
        
        try {
            $adminPdo->beginTransaction();
            
            // Insert order into admin database
            $adminOrderStmt = $adminPdo->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, payment_details, subtotal, delivery_fee, total_amount, estimated_delivery_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $adminOrderStmt->execute([
                $userOrder['order_number'],
                $userOrder['user_id'],
                $userOrder['customer_name'],
                $userOrder['customer_email'],
                $userOrder['customer_phone'],
                $userOrder['customer_address'],
                $userOrder['payment_method'],
                $userOrder['payment_details'],
                $userOrder['subtotal'],
                $userOrder['delivery_fee'],
                $userOrder['total_amount'],
                $userOrder['estimated_delivery_date'],
                $userOrder['status']
            ]);
            
            $adminOrderId = $adminPdo->lastInsertId();
            
            // Get order items from user database
            $userOrderItems = $userPdo->prepare("SELECT * FROM user_order_items WHERE user_order_id = ?");
            $userOrderItems->execute([$userOrder['id']]);
            $items = $userOrderItems->fetchAll();
            
            // Insert order items into admin database
            $adminOrderItemStmt = $adminPdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $adminOrderItemStmt->execute([
                    $adminOrderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
            }
            
            // Get status history from user database
            $userStatusHistory = $userPdo->prepare("SELECT * FROM user_order_status_history WHERE user_order_id = ? ORDER BY created_at ASC");
            $userStatusHistory->execute([$userOrder['id']]);
            $statusHistory = $userStatusHistory->fetchAll();
            
            // Insert status history into admin database
            $adminStatusStmt = $adminPdo->prepare('INSERT INTO order_status_history (order_id, status, notes, created_at) VALUES (?, ?, ?, ?)');
            foreach ($statusHistory as $status) {
                $adminStatusStmt->execute([
                    $adminOrderId,
                    $status['status'],
                    $status['notes'],
                    $status['created_at']
                ]);
            }
            
            $adminPdo->commit();
            echo "✅ Synced order {$userOrder['order_number']} to admin database<br>";
            $syncedCount++;
            
        } catch (Exception $e) {
            $adminPdo->rollBack();
            echo "❌ Failed to sync order {$userOrder['order_number']}: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<hr>";
    echo "<h3>✅ Order Sync Complete!</h3>";
    echo "<p><strong>Synced:</strong> $syncedCount orders</p>";
    echo "<p><strong>Skipped:</strong> $skippedCount orders (already exist)</p>";
    echo "<p><strong>Total processed:</strong> " . count($userOrders) . " orders</p>";
    
    if ($syncedCount > 0) {
        echo "<p style='color: green;'><strong>✅ Orders are now visible in admin panel!</strong></p>";
        echo "<p><a href='admin_orders.php' target='_blank'>View Admin Orders</a></p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure both databases are set up correctly!</p>";
}
?>
