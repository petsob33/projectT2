<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include database connection file
require_once "includes/db.php";

$sql = "
-- Table for users (admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

-- Table for photos
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
";

try {
    $pdo->exec($sql);
    echo "Database tables created successfully.";
} catch (PDOException $e) {
    die("ERROR: Could not create tables. " . $e->getMessage());
}

// Close connection
unset($pdo);
?>