<?php
// Update User Database - Add Missing Order Tables and Columns
echo "<h2>Updating User Database...</h2>";

try {
    require_once __DIR__ . '/user_config.php';
    
    // Connect to user database
    $pdo = get_user_pdo();
    echo "✅ Connected to user database successfully!<br>";
    
    // Check if user_orders table exists and get its structure
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Current tables: " . implode(', ', $tables) . "<br>";
    
    if (in_array('user_orders', $tables)) {
        // Check existing columns
        $columns = $pdo->query("SHOW COLUMNS FROM user_orders")->fetchAll(PDO::FETCH_COLUMN);
        echo "📋 user_orders columns: " . implode(', ', $columns) . "<br>";
        
        // Add missing columns if they don't exist
        $missingColumns = [
            'customer_name' => "ALTER TABLE user_orders ADD COLUMN customer_name VARCHAR(200) NOT NULL AFTER order_number",
            'customer_email' => "ALTER TABLE user_orders ADD COLUMN customer_email VARCHAR(200) NOT NULL AFTER customer_name",
            'customer_phone' => "ALTER TABLE user_orders ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_email",
            'customer_address' => "ALTER TABLE user_orders ADD COLUMN customer_address TEXT NULL AFTER customer_phone",
            'payment_method' => "ALTER TABLE user_orders ADD COLUMN payment_method ENUM('cod','gcash') NOT NULL AFTER customer_address",
            'payment_details' => "ALTER TABLE user_orders ADD COLUMN payment_details TEXT NULL AFTER payment_method",
            'subtotal' => "ALTER TABLE user_orders ADD COLUMN subtotal DECIMAL(12,2) NOT NULL AFTER payment_details",
            'delivery_fee' => "ALTER TABLE user_orders ADD COLUMN delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal"
        ];
        
        foreach ($missingColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $pdo->exec($sql);
                echo "✅ Added column: $column<br>";
            } else {
                echo "ℹ️ Column already exists: $column<br>";
            }
        }
    } else {
        // Create user_orders table if it doesn't exist
        $createUserOrders = "
        CREATE TABLE IF NOT EXISTS user_orders (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          order_number VARCHAR(20) NOT NULL UNIQUE,
          customer_name VARCHAR(200) NOT NULL,
          customer_email VARCHAR(200) NOT NULL,
          customer_phone VARCHAR(20) NULL,
          customer_address TEXT NULL,
          payment_method ENUM('cod','gcash') NOT NULL,
          payment_details TEXT NULL,
          subtotal DECIMAL(12,2) NOT NULL,
          delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          total_amount DECIMAL(12,2) NOT NULL,
          status ENUM('pending','confirmed','preparing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
          estimated_delivery_date DATE NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user (user_id),
          KEY idx_order_number (order_number),
          KEY idx_status (status),
          CONSTRAINT fk_uo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createUserOrders);
        echo "✅ Created user_orders table<br>";
    }
    
    // Create user_order_items table if it doesn't exist
    if (!in_array('user_order_items', $tables)) {
        $createUserOrderItems = "
        CREATE TABLE IF NOT EXISTS user_order_items (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_order_id BIGINT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NOT NULL,
          product_name VARCHAR(200) NOT NULL,
          quantity DECIMAL(12,3) NOT NULL,
          unit_price DECIMAL(12,2) NOT NULL,
          total_price DECIMAL(12,2) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_order (user_order_id),
          CONSTRAINT fk_uoi_user_order FOREIGN KEY (user_order_id) REFERENCES user_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createUserOrderItems);
        echo "✅ Created user_order_items table<br>";
    } else {
        echo "ℹ️ user_order_items table already exists<br>";
    }
    
    // Create user_order_status_history table if it doesn't exist
    if (!in_array('user_order_status_history', $tables)) {
        $createUserOrderStatusHistory = "
        CREATE TABLE IF NOT EXISTS user_order_status_history (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_order_id BIGINT UNSIGNED NOT NULL,
          status ENUM('pending','confirmed','preparing','shipped','delivered','cancelled') NOT NULL,
          notes TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_order (user_order_id),
          CONSTRAINT fk_uosh_user_order FOREIGN KEY (user_order_id) REFERENCES user_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createUserOrderStatusHistory);
        echo "✅ Created user_order_status_history table<br>";
    } else {
        echo "ℹ️ user_order_status_history table already exists<br>";
    }
    
    // Verify final table structure
    $finalTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<hr>";
    echo "<h3>✅ User Database Update Complete!</h3>";
    echo "<p><strong>Final tables:</strong> " . implode(', ', $finalTables) . "</p>";
    
    $requiredTables = ['users', 'carts', 'cart_items', 'user_orders', 'user_order_items', 'user_order_status_history'];
    $missingTables = array_diff($requiredTables, $finalTables);
    
    if (empty($missingTables)) {
        echo "<p style='color: green;'><strong>✅ All required tables are present!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Missing tables: " . implode(', ', $missingTables) . "</strong></p>";
    }
    
    echo "<p><a href='checkout.php'>Test Checkout Now</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure to run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
