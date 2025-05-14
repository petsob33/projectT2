<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Set header to return JSON
header('Content-Type: application/json');

// Check if the user is logged in
if (!is_logged_in()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Uživatel není přihlášen.']);
    exit;
}

$user_id = $_SESSION['id'];

// Check if the user is part of a pair
$pair_info = null;
$sql_check_pair = "SELECT p.id, u.id AS other_user_id, u.username AS other_username
                   FROM pairs p
                   JOIN users u ON (u.id = p.user1_id OR u.id = p.user2_id)
                   WHERE (p.user1_id = :user_id OR p.user2_id = :user_id) AND u.id != :user_id"; // Exclude the current user
if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_pair->execute()) {
        $pair_info = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("Error checking pair status: " . $stmt_check_pair->errorInfo()[2]);
    }
}
unset($stmt_check_pair);

if ($pair_info) {
    // User is part of a pair
    echo json_encode([
        'is_paired' => true,
        'pair_id' => $pair_info['id'],
        'other_user' => [
            'id' => $pair_info['other_user_id'],
            'username' => $pair_info['other_username']
        ]
    ]);
} else {
    // User is not part of a pair
    echo json_encode(['is_paired' => false]);
}

// Close connection
unset($pdo);

?>