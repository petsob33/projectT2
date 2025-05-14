<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include database connection file
require_once "../includes/db.php";

$sql = "ALTER TABLE photos ADD COLUMN date DATE NULL AFTER description";

try {
    $pdo->exec($sql);
    echo "Column 'date' added to 'photos' table successfully.";
} catch (PDOException $e) {
    // Check if the error is because the column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'date' already exists in 'photos' table.";
    } else {
        die("ERROR: Could not add column 'date'. " . $e->getMessage());
    }
}

// Close connection
unset($pdo);
?>