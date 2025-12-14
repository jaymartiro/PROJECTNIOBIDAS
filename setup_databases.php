<?php
// Database Setup Script
// This script creates the separate databases for admin and user systems

echo "<h2>Setting up separate databases...</h2>";

// Admin database setup
echo "<h3>Creating Admin Database...</h3>";
try {
    $adminPdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $adminPdo->exec("CREATE DATABASE IF NOT EXISTS obidas_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $adminPdo->exec("USE obidas_admin");
    
    // Read and execute admin database schema
    $adminSchema = file_get_contents(__DIR__ . '/admin_database.sql');
    $adminPdo->exec($adminSchema);
    
    echo "✅ Admin database created successfully!<br>";
} catch (Exception $e) {
    echo "❌ Error creating admin database: " . $e->getMessage() . "<br>";
}

// User database setup
echo "<h3>Creating User Database...</h3>";
try {
    $userPdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $userPdo->exec("CREATE DATABASE IF NOT EXISTS obidas_user CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $userPdo->exec("USE obidas_user");
    
    // Read and execute user database schema
    $userSchema = file_get_contents(__DIR__ . '/user_database.sql');
    $userPdo->exec($userSchema);
    
    echo "✅ User database created successfully!<br>";
} catch (Exception $e) {
    echo "❌ Error creating user database: " . $e->getMessage() . "<br>";
}

echo "<h3>Database Setup Complete!</h3>";
echo "<p><strong>Admin Database:</strong> obidas_admin</p>";
echo "<p><strong>User Database:</strong> obidas_user</p>";
echo "<p><strong>Default Admin Login:</strong> admin@obidas.com / admin123</p>";

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li><a href='admin_login.php'>Test Admin Login</a></li>";
echo "<li><a href='signup.php'>Create User Account</a></li>";
echo "<li><a href='index.php'>Go to Homepage</a></li>";
echo "</ul>";
?>
