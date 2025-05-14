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
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['id'];
$debug_info = ['user_id' => $user_id];

// Check if the user is part of a pair
$pair_id = null;
$partner_id = null;
$sql_check_pair = "SELECT user1_id, user2_id, id FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_pair->execute()) {
        $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
        if ($pair) {
            $pair_id = $pair['id'];
            // Determine partner's ID
            $partner_id = ($pair['user1_id'] == $user_id) ? $pair['user2_id'] : $pair['user1_id'];
        }
    } else {
        $debug_info['pair_check_error'] = $stmt_check_pair->errorInfo()[2];
    }
}
unset($stmt_check_pair);

$debug_info['pair_id'] = $pair_id;
$debug_info['partner_id'] = $partner_id;

error_log("DEBUG: get_random_photo - User ID: " . $user_id);
error_log("DEBUG: get_random_photo - Pair ID before query: " . ($pair_id !== null ? $pair_id : "NULL"));
error_log("DEBUG: get_random_photo - Partner ID before query: " . ($partner_id !== null ? $partner_id : "NULL"));

// Fetch a random photo filename from the database based on pairing status
$photoData = null;
$sql = "SELECT filename FROM photos WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($pair_id !== null) {
    // Include photos from the pair and the partner
    $sql = "SELECT filename FROM photos WHERE user_id = :user_id OR user_id = :partner_id OR (user_id IS NULL AND pair_id = :pair_id)";
    $params[':partner_id'] = $partner_id;
    $params[':pair_id'] = $pair_id;
}

$sql .= " ORDER BY RAND() LIMIT 1";

$debug_info['sql_query'] = $sql;
$debug_info['sql_params'] = $params;

if ($stmt = $pdo->prepare($sql)) {
    if ($stmt->execute($params)) {
        $photoData = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $debug_info['photo_fetch_error'] = $stmt->errorInfo()[2];
    }
}
unset($stmt);

$response_data = $photoData ? $photoData : ['filename' => null];
$response_data['debug'] = $debug_info; // Include debug info in the response

echo json_encode($response_data);

// Close connection
unset($pdo);

?>