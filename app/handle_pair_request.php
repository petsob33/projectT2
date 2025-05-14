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

// Check if request_id and action are provided in the POST request
if (!isset($_POST['request_id']) || empty(trim($_POST['request_id'])) || !isset($_POST['action']) || empty(trim($_POST['action']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Chybí ID žádosti nebo akce.']);
    exit;
}

$user_id = $_SESSION['id'];
$request_id = trim($_POST['request_id']);
$action = trim($_POST['action']); // 'accept' or 'reject'

// Validate action
if ($action !== 'accept' && $action !== 'reject') {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Neplatná akce.']);
    exit;
}

// Fetch the pair request to verify it exists and the current user is the recipient
$request = null;
$sql_fetch_request = "SELECT id, requester_id, recipient_id, status FROM pair_requests WHERE id = :id";
if ($stmt_fetch_request = $pdo->prepare($sql_fetch_request)) {
    $stmt_fetch_request->bindParam(":id", $request_id, PDO::PARAM_INT);
    if ($stmt_fetch_request->execute()) {
        $request = $stmt_fetch_request->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching pair request: " . $stmt_fetch_request->errorInfo()[2]);
    }
}
unset($stmt_fetch_request);

if ($request === null) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Žádost o párování nebyla nalezena.']);
    exit;
}

// Check if the current user is the recipient of the request
if ($request['recipient_id'] != $user_id) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Nemáte oprávnění k provedení této akce.']);
    exit;
}

// Check if the request is still pending
if ($request['status'] !== 'pending') {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'Žádost o párování již byla zpracována.']);
    exit;
}

// Update the request status
$new_status = ($action === 'accept') ? 'accepted' : 'rejected';
$sql_update_request = "UPDATE pair_requests SET status = :status WHERE id = :id";
if ($stmt_update_request = $pdo->prepare($sql_update_request)) {
    $stmt_update_request->bindParam(":status", $new_status, PDO::PARAM_STR);
    $stmt_update_request->bindParam(":id", $request_id, PDO::PARAM_INT);
    if ($stmt_update_request->execute()) {

        if ($action === 'accept') {
            // If accepted, create a new pair entry
            $user1_id = min($request['requester_id'], $request['recipient_id']); // Ensure consistent order
            $user2_id = max($request['requester_id'], $request['recipient_id']);

            $sql_create_pair = "INSERT INTO pairs (user1_id, user2_id) VALUES (:user1_id, :user2_id)";
            if ($stmt_create_pair = $pdo->prepare($sql_create_pair)) {
                $stmt_create_pair->bindParam(":user1_id", $user1_id, PDO::PARAM_INT);
                $stmt_create_pair->bindParam(":user2_id", $user2_id, PDO::PARAM_INT);
                if ($stmt_create_pair->execute()) {
                    http_response_code(200); // OK
                    echo json_encode(['message' => 'Žádost o párování byla přijata. Nyní jste pár!']);
                } else {
                    // If pair creation fails, revert request status and log error
                    $sql_revert_request = "UPDATE pair_requests SET status = 'pending' WHERE id = :id";
                    $stmt_revert_request = $pdo->prepare($sql_revert_request);
                    $stmt_revert_request->bindParam(":id", $request_id, PDO::PARAM_INT);
                    $stmt_revert_request->execute(); // Attempt to revert, ignore errors here

                    http_response_code(500); // Internal Server Error
                    error_log("Error creating pair: " . $stmt_create_pair->errorInfo()[2]);
                    echo json_encode(['error' => 'Nepodařilo se vytvořit pár. Zkuste to prosím znovu.']);
                }
                unset($stmt_create_pair);
            } else {
                 http_response_code(500); // Internal Server Error
                 error_log("Error preparing create pair statement: " . $pdo->errorInfo()[2]);
                 echo json_encode(['error' => 'Interní chyba serveru.']);
            }
        } else {
            // If rejected
            http_response_code(200); // OK
            echo json_encode(['message' => 'Žádost o párování byla odmítnuta.']);
        }

    } else {
        http_response_code(500); // Internal Server Error
        error_log("Error updating pair request status: " . $stmt_update_request->errorInfo()[2]);
        echo json_encode(['error' => 'Nepodařilo se zpracovat žádost o párování. Zkuste to prosím znovu.']);
    }
} else {
    http_response_code(500); // Internal Server Error
    error_log("Error preparing update pair request statement: " . $pdo->errorInfo()[2]);
    echo json_encode(['error' => 'Interní chyba serveru.']);
}
unset($stmt_update_request);

// Close connection
unset($pdo);

?>