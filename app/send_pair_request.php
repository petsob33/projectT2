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

// Check if recipient_username is provided in the POST request
if (!isset($_POST['recipient_username']) || empty(trim($_POST['recipient_username']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Není zadáno uživatelské jméno příjemce.']);
    exit;
}

$requester_id = $_SESSION['id'];
$recipient_username = trim($_POST['recipient_username']);

// Find the recipient user's ID
$recipient_id = null;
$sql_find_recipient = "SELECT id FROM users WHERE username = :username";
if ($stmt_find_recipient = $pdo->prepare($sql_find_recipient)) {
    $stmt_find_recipient->bindParam(":username", $recipient_username, PDO::PARAM_STR);
    if ($stmt_find_recipient->execute()) {
        if ($stmt_find_recipient->rowCount() == 1) {
            $row = $stmt_find_recipient->fetch(PDO::FETCH_ASSOC);
            $recipient_id = $row['id'];
        }
    } else {
        error_log("Error finding recipient user: " . $stmt_find_recipient->errorInfo()[2]);
    }
}
unset($stmt_find_recipient);

if ($recipient_id === null) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Uživatel s tímto jménem nebyl nalezen.']);
    exit;
}

// Prevent sending request to self
if ($requester_id === $recipient_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Nelze odeslat žádost sám sobě.']);
    exit;
}

// Check if a request already exists between these two users (in either direction)
$sql_check_request = "SELECT id FROM pair_requests WHERE (requester_id = :requester_id AND recipient_id = :recipient_id) OR (requester_id = :recipient_id AND recipient_id = :requester_id)";
if ($stmt_check_request = $pdo->prepare($sql_check_request)) {
    $stmt_check_request->bindParam(":requester_id", $requester_id, PDO::PARAM_INT);
    $stmt_check_request->bindParam(":recipient_id", $recipient_id, PDO::PARAM_INT);
    if ($stmt_check_request->execute()) {
        if ($stmt_check_request->rowCount() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Žádost o párování již existuje.']);
            exit;
        }
    } else {
        error_log("Error checking existing request: " . $stmt_check_request->errorInfo()[2]);
    }
}
unset($stmt_check_request);


// Insert the new pair request into the database
$sql_insert_request = "INSERT INTO pair_requests (requester_id, recipient_id) VALUES (:requester_id, :recipient_id)";
if ($stmt_insert_request = $pdo->prepare($sql_insert_request)) {
    $stmt_insert_request->bindParam(":requester_id", $requester_id, PDO::PARAM_INT);
    $stmt_insert_request->bindParam(":recipient_id", $recipient_id, PDO::PARAM_INT);
    if ($stmt_insert_request->execute()) {
        http_response_code(201); // Created
        echo json_encode(['message' => 'Žádost o párování byla úspěšně odeslána.']);
    } else {
        http_response_code(500); // Internal Server Error
        error_log("Error inserting pair request: " . $stmt_insert_request->errorInfo()[2]);
        echo json_encode(['error' => 'Nepodařilo se odeslat žádost o párování. Zkuste to prosím znovu.']);
    }
} else {
     http_response_code(500); // Internal Server Error
     error_log("Error preparing insert pair request statement: " . $pdo->errorInfo()[2]);
     echo json_encode(['error' => 'Interní chyba serveru.']);
}
unset($stmt_insert_request);

// Close connection
unset($pdo);

?>