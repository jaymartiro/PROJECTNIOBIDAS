<?php
// Add Sample Products to Admin Database
echo "<h2>Adding Sample Products to Admin Database...</h2>";

try {
    require_once __DIR__ . '/admin_config.php';
    
    // Connect to admin database
    $pdo = get_admin_pdo();
    echo "✅ Connected to admin database successfully!<br>";
    
    // Sample products data (same as used in user system)
    $sampleProducts = [
        ['id' => 1, 'sku' => 'SKU-0001', 'name' => 'Pork Longanisa', 'image_path' => 'images/Pork Longanisa.jpg', 'unit_cost' => 150.00, 'markup_pct' => 30.00, 'tax_pct' => 12.00],
        ['id' => 2, 'sku' => 'SKU-0002', 'name' => 'Longanisa Hamonada', 'image_path' => 'images/Longanisa Hamonada.jpg', 'unit_cost' => 160.00, 'markup_pct' => 30.00, 'tax_pct' => 12.00],
        ['id' => 3, 'sku' => 'SKU-0003', 'name' => 'Pork Tocino', 'image_path' => 'images/Pork Tocino.jpg', 'unit_cost' => 140.00, 'markup_pct' => 30.00, 'tax_pct' => 12.00],
        ['id' => 4, 'sku' => 'SKU-0004', 'name' => 'Chicken Hotdog', 'image_path' => 'images/Chicken Hotdog.jpg', 'unit_cost' => 120.00, 'markup_pct' => 30.00, 'tax_pct' => 12.00],
        ['id' => 5, 'sku' => 'SKU-0005', 'name' => 'Chicken Cheese Dog', 'image_path' => 'images/Chicken Cheese dog.jpg', 'unit_cost' => 130.00, 'markup_pct' => 30.00, 'tax_pct' => 12.00]
    ];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($sampleProducts as $product) {
        // Check if product already exists
        $existing = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $existing->execute([$product['id']]);
        
        if ($existing->fetch()) {
            echo "ℹ️ Product {$product['name']} (ID: {$product['id']}) already exists<br>";
            $skippedCount++;
            continue;
        }
        
        // Insert product
        $insert = $pdo->prepare('INSERT INTO products (id, sku, name, description, image_path, unit_cost, markup_pct, tax_pct, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $product['id'],
            $product['sku'],
            $product['name'],
            'Sample product for order management',
            $product['image_path'],
            $product['unit_cost'],
            $product['markup_pct'],
            $product['tax_pct'],
            1
        ]);
        
        echo "✅ Added product: {$product['name']} (ID: {$product['id']})<br>";
        $addedCount++;
    }
    
    echo "<hr>";
    echo "<h3>✅ Sample Products Added!</h3>";
    echo "<p><strong>Added:</strong> $addedCount products</p>";
    echo "<p><strong>Skipped:</strong> $skippedCount products (already exist)</p>";
    
    if ($addedCount > 0) {
        echo "<p style='color: green;'><strong>✅ Sample products are now available for order sync!</strong></p>";
        echo "<p><a href='sync_user_orders_to_admin.php'>Sync User Orders Now</a></p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure the admin database is set up correctly!</p>";
}
?>
