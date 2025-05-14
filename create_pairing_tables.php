<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "includes/db.php";

echo "Spouštění migrace databáze pro funkci párování uživatelů...\n";

try {
    // SQL statement to create the pair_requests table
    $sql_pair_requests = "CREATE TABLE pair_requests (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        requester_id INT(11) NOT NULL,
        recipient_id INT(11) NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_request (requester_id, recipient_id)
    );";

    // SQL statement to create the pairs table
    $sql_pairs = "CREATE TABLE pairs (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user1_id INT(11) NOT NULL,
        user2_id INT(11) NOT NULL,
        established_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_pair (user1_id, user2_id)
    );";

    // SQL statement to add pair_id column to photos table
    $sql_alter_photos = "ALTER TABLE photos
        ADD COLUMN pair_id INT(11) NULL,
        ADD CONSTRAINT fk_photos_pair_id
        FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE SET NULL;";

    // Execute the SQL statements
    $pdo->exec($sql_pair_requests);
    echo "Tabulka 'pair_requests' vytvořena.\n";

    $pdo->exec($sql_pairs);
    echo "Tabulka 'pairs' vytvořena.\n";

    // Check if pair_id column already exists before adding
    $column_exists = false;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM photos LIKE 'pair_id'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $column_exists = true;
    }
    unset($stmt);

    if (!$column_exists) {
        $pdo->exec($sql_alter_photos);
        echo "Sloupec 'pair_id' přidán do tabulky 'photos'.\n";
    } else {
        echo "Sloupec 'pair_id' již v tabulce 'photos' existuje. Přeskakuji přidání sloupce.\n";
    }


    echo "Migrace databáze dokončena úspěšně.\n";

} catch (PDOException $e) {
    die("Chyba při provádění migrace databáze: " . $e->getMessage());
}

// Close connection
unset($pdo);

?>