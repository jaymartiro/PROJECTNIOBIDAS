<?php
// Database Update Script - Add SKU Column
// This script adds the missing SKU column to existing products table

echo "<h2>Adding SKU Column to Products Table...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    $adminPdo = get_admin_pdo();
    
    echo "✅ Connected to admin database successfully!<br>";
    
    // Check if SKU column exists
    $columns = $adminPdo->query("SHOW COLUMNS FROM products LIKE 'sku'")->fetchAll();
    
    if (empty($columns)) {
        echo "📋 Adding SKU column to products table...<br>";
        
        // Add the SKU column
        $adminPdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) NOT NULL UNIQUE AFTER id");
        
        // Add index for SKU
        $adminPdo->exec("ALTER TABLE products ADD KEY idx_sku (sku)");
        
        echo "✅ SKU column added successfully!<br>";
        
        // Update existing products with default SKU values
        $products = $adminPdo->query("SELECT id, name FROM products WHERE sku = '' OR sku IS NULL")->fetchAll();
        if (!empty($products)) {
            echo "📋 Updating existing products with SKU values...<br>";
            foreach ($products as $product) {
                $sku = 'SKU-' . str_pad($product['id'], 4, '0', STR_PAD_LEFT);
                $stmt = $adminPdo->prepare("UPDATE products SET sku = ? WHERE id = ?");
                $stmt->execute([$sku, $product['id']]);
            }
            echo "✅ Updated " . count($products) . " products with SKU values!<br>";
        }
        
    } else {
        echo "✅ SKU column already exists!<br>";
    }
    
    // Verify the table structure
    $columns = $adminPdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Products table columns: " . implode(', ', $columns) . "<br>";
    
    echo "<hr>";
    echo "<h3>SKU Column Update Complete!</h3>";
    echo "<p><a href='admin_dashboard.php'>Test Admin Dashboard</a></p>";
    echo "<p><a href='product_new.php'>Test Add Product</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure the admin database exists. Run <a href='setup_databases.php'>setup_databases.php</a> first!</p>";
}
?>
