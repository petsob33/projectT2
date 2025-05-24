<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "./projectT2/includes/db.php";

// Set header to return plain text
header('Content-Type: text/plain');

echo "Starting database setup for group functionality...\n\n";

// Create groups table
$sql_create_groups = "
CREATE TABLE IF NOT EXISTS groups (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    established_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NULL,
    created_by INT(11) NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($sql_create_groups);
    echo "✓ Groups table created successfully\n";
} catch (PDOException $e) {
    echo "✗ Error creating groups table: " . $e->getMessage() . "\n";
}

// Create group_members table
$sql_create_group_members = "
CREATE TABLE IF NOT EXISTS group_members (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (group_id, user_id)
)";

try {
    $pdo->exec($sql_create_group_members);
    echo "✓ Group members table created successfully\n";
} catch (PDOException $e) {
    echo "✗ Error creating group_members table: " . $e->getMessage() . "\n";
}

// Create group_invitations table
$sql_create_group_invitations = "
CREATE TABLE IF NOT EXISTS group_invitations (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_id INT(11) NOT NULL,
    inviter_id INT(11) NOT NULL,
    invitee_id INT(11) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invitation (group_id, invitee_id)
)";

try {
    $pdo->exec($sql_create_group_invitations);
    echo "✓ Group invitations table created successfully\n";
} catch (PDOException $e) {
    echo "✗ Error creating group_invitations table: " . $e->getMessage() . "\n";
}

// Add group_id column to events table if it doesn't exist
$sql_check_events_column = "SHOW COLUMNS FROM events LIKE 'group_id'";
$stmt = $pdo->query($sql_check_events_column);
if ($stmt->rowCount() == 0) {
    $sql_alter_events = "ALTER TABLE events ADD COLUMN group_id INT(11) NULL, ADD FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE";
    try {
        $pdo->exec($sql_alter_events);
        echo "✓ Added group_id column to events table\n";
    } catch (PDOException $e) {
        echo "✗ Error adding group_id column to events table: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ group_id column already exists in events table\n";
}

// Add group_id column to photos table if it doesn't exist
$sql_check_photos_column = "SHOW COLUMNS FROM photos LIKE 'group_id'";
$stmt = $pdo->query($sql_check_photos_column);
if ($stmt->rowCount() == 0) {
    $sql_alter_photos = "ALTER TABLE photos ADD COLUMN group_id INT(11) NULL, ADD FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL";
    try {
        $pdo->exec($sql_alter_photos);
        echo "✓ Added group_id column to photos table\n";
    } catch (PDOException $e) {
        echo "✗ Error adding group_id column to photos table: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ group_id column already exists in photos table\n";
}

// Add pair_id column to photos table if it doesn't exist
$sql_check_photos_pair_column = "SHOW COLUMNS FROM photos LIKE 'pair_id'";
$stmt = $pdo->query($sql_check_photos_pair_column);
if ($stmt->rowCount() == 0) {
    $sql_alter_photos = "ALTER TABLE photos ADD COLUMN pair_id INT(11) NULL, ADD FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE SET NULL";
    try {
        $pdo->exec($sql_alter_photos);
        echo "✓ Added pair_id column to photos table\n";
    } catch (PDOException $e) {
        echo "✗ Error adding pair_id column to photos table: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ pair_id column already exists in photos table\n";
}

// Add group_id column to pairs table if it doesn't exist
$sql_check_pairs_column = "SHOW COLUMNS FROM pairs LIKE 'group_id'";
$stmt = $pdo->query($sql_check_pairs_column);
if ($stmt->rowCount() == 0) {
    $sql_alter_pairs = "ALTER TABLE pairs ADD COLUMN group_id INT(11) NULL, ADD FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL";
    try {
        $pdo->exec($sql_alter_pairs);
        echo "✓ Added group_id column to pairs table\n";
    } catch (PDOException $e) {
        echo "✗ Error adding group_id column to pairs table: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ group_id column already exists in pairs table\n";
}

echo "\nDatabase setup completed!\n";
echo "You can now use the group functionality.\n";
echo "Visit http://localhost:8080/app/group_invitations.php to create and manage groups.\n";

// Close connection
unset($pdo);
?>