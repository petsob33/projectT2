<?php
define('INCLUDE_CHECK', true);
require 'includes/db.php';

$sql = "CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(255),
    date DATE
)";

try {
    $pdo->exec($sql);
    echo "Table 'photos' created successfully or already exists.";
} catch (PDOException $e) {
    die("ERROR: Could not create table. " . $e->getMessage());
}
?>