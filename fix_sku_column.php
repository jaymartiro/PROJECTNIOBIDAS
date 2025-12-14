<?php
// Quick Fix: Add SKU column to products table
try {
    $pdo = new PDO('mysql:host=localhost;dbname=obidas_admin;charset=utf8mb4', 'root', '');
    
    // Check if SKU column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'sku'")->fetchAll();
    
    if (empty($columns)) {
        // Add the SKU column
        $pdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) NOT NULL UNIQUE AFTER id");
        $pdo->exec("ALTER TABLE products ADD KEY idx_sku (sku)");
        
        // Update existing products with default SKU values
        $products = $pdo->query("SELECT id FROM products")->fetchAll();
        foreach ($products as $product) {
            $sku = 'SKU-' . str_pad($product['id'], 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE products SET sku = ? WHERE id = ?");
            $stmt->execute([$sku, $product['id']]);
        }
        
        echo "✅ SKU column added successfully!";
    } else {
        echo "✅ SKU column already exists!";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
