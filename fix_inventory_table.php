<?php
// Quick Fix: Add missing inventory_movements table
try {
    $pdo = new PDO('mysql:host=localhost;dbname=obidas_admin;charset=utf8mb4', 'root', '');
    
    // Create the missing table
    $sql = "
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
    
    $pdo->exec($sql);
    echo "✅ inventory_movements table created successfully!";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
