<?php
// Database Update Script
// This script adds the missing inventory_movements table to existing admin database

echo "<h2>Updating Admin Database...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    $adminPdo = get_admin_pdo();
    
    echo "✅ Connected to admin database successfully!<br>";
    
    // Check if inventory_movements table exists
    $tables = $adminPdo->query("SHOW TABLES LIKE 'inventory_movements'")->fetchAll();
    
    if (empty($tables)) {
        echo "📋 Creating inventory_movements table...<br>";
        
        // Create the inventory_movements table
        $createTable = "
        CREATE TABLE IF NOT EXISTS inventory_movements (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          product_id BIGINT UNSIGNED NOT NULL,
          movement_type ENUM('in','out') NOT NULL,
          quantity DECIMAL(12,3) NOT NULL,
          note TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_product (product_id),
          KEY idx_type (movement_type),
          KEY idx_created_at (created_at),
          CONSTRAINT fk_im_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $adminPdo->exec($createTable);
        echo "✅ inventory_movements table created successfully!<br>";
        
    } else {
        echo "✅ inventory_movements table already exists!<br>";
    }
    
    // Check if SKU column exists in products table
    $columns = $adminPdo->query("SHOW COLUMNS FROM products LIKE 'sku'")->fetchAll();
    
    if (empty($columns)) {
        echo "📋 Adding SKU column to products table...<br>";
        
        // Add the SKU column
        $adminPdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) NOT NULL UNIQUE AFTER id");
        $adminPdo->exec("ALTER TABLE products ADD KEY idx_sku (sku)");
        
        // Update existing products with default SKU values
        $products = $adminPdo->query("SELECT id FROM products")->fetchAll();
        foreach ($products as $product) {
            $sku = 'SKU-' . str_pad($product['id'], 4, '0', STR_PAD_LEFT);
            $stmt = $adminPdo->prepare("UPDATE products SET sku = ? WHERE id = ?");
            $stmt->execute([$sku, $product['id']]);
        }
        
        echo "✅ SKU column added successfully!<br>";
    } else {
        echo "✅ SKU column already exists!<br>";
    }
    
    // Verify all required tables exist
    $allTables = $adminPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Admin database tables: " . implode(', ', $allTables) . "<br>";
    
    $requiredTables = ['admin_users', 'products', 'orders', 'order_items', 'order_status_history', 'inventory_movements'];
    $missingTables = array_diff($requiredTables, $allTables);
    
    if (empty($missingTables)) {
        echo "✅ All required tables are present!<br>";
    } else {
        echo "⚠️ Missing tables: " . implode(', ', $missingTables) . "<br>";
    }
    
    echo "<hr>";
    echo "<h3>Database Update Complete!</h3>";
    echo "<p><a href='admin_dashboard.php'>Test Admin Dashboard</a></p>";
    echo "<p><a href='test_admin_dashboard.php'>Run Admin Dashboard Test</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure the admin database exists. Run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
