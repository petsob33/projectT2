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

// Check if recipient_username and group_id are provided in the POST request
if (!isset($_POST['recipient_username']) || empty(trim($_POST['recipient_username'])) || 
    !isset($_POST['group_id']) || empty(trim($_POST['group_id']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Není zadáno uživatelské jméno příjemce nebo ID skupiny.']);
    exit;
}

$inviter_id = $_SESSION['id'];
$recipient_username = trim($_POST['recipient_username']);
$group_id = trim($_POST['group_id']);

// Verify the group exists and the user is a member
$is_member = false;
$sql_check_membership = "SELECT id FROM group_members WHERE group_id = :group_id AND user_id = :user_id";
if ($stmt_check_membership = $pdo->prepare($sql_check_membership)) {
    $stmt_check_membership->bindParam(":group_id", $group_id, PDO::PARAM_INT);
    $stmt_check_membership->bindParam(":user_id", $inviter_id, PDO::PARAM_INT);
    if ($stmt_check_membership->execute()) {
        if ($stmt_check_membership->rowCount() > 0) {
            $is_member = true;
        }
    } else {
        error_log("Error checking group membership: " . $stmt_check_membership->errorInfo()[2]);
    }
}
unset($stmt_check_membership);

if (!$is_member) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Nemáte oprávnění pozvat uživatele do této skupiny.']);
    exit;
}

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

// Prevent sending invitation to self
if ($inviter_id === $recipient_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Nelze odeslat pozvánku sám sobě.']);
    exit;
}

// Check if the recipient is already a member of the group
$is_already_member = false;
$sql_check_recipient_membership = "SELECT id FROM group_members WHERE group_id = :group_id AND user_id = :user_id";
if ($stmt_check_recipient_membership = $pdo->prepare($sql_check_recipient_membership)) {
    $stmt_check_recipient_membership->bindParam(":group_id", $group_id, PDO::PARAM_INT);
    $stmt_check_recipient_membership->bindParam(":user_id", $recipient_id, PDO::PARAM_INT);
    if ($stmt_check_recipient_membership->execute()) {
        if ($stmt_check_recipient_membership->rowCount() > 0) {
            $is_already_member = true;
        }
    } else {
        error_log("Error checking recipient membership: " . $stmt_check_recipient_membership->errorInfo()[2]);
    }
}
unset($stmt_check_recipient_membership);

if ($is_already_member) {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'Uživatel je již členem této skupiny.']);
    exit;
}

// Check if an invitation already exists for this user and group
$sql_check_invitation = "SELECT id FROM group_invitations WHERE group_id = :group_id AND invitee_id = :invitee_id AND status = 'pending'";
if ($stmt_check_invitation = $pdo->prepare($sql_check_invitation)) {
    $stmt_check_invitation->bindParam(":group_id", $group_id, PDO::PARAM_INT);
    $stmt_check_invitation->bindParam(":invitee_id", $recipient_id, PDO::PARAM_INT);
    if ($stmt_check_invitation->execute()) {
        if ($stmt_check_invitation->rowCount() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Pozvánka do skupiny již existuje.']);
            exit;
        }
    } else {
        error_log("Error checking existing invitation: " . $stmt_check_invitation->errorInfo()[2]);
    }
}
unset($stmt_check_invitation);

// Insert the new group invitation into the database
$sql_insert_invitation = "INSERT INTO group_invitations (group_id, inviter_id, invitee_id) VALUES (:group_id, :inviter_id, :invitee_id)";
if ($stmt_insert_invitation = $pdo->prepare($sql_insert_invitation)) {
    $stmt_insert_invitation->bindParam(":group_id", $group_id, PDO::PARAM_INT);
    $stmt_insert_invitation->bindParam(":inviter_id", $inviter_id, PDO::PARAM_INT);
    $stmt_insert_invitation->bindParam(":invitee_id", $recipient_id, PDO::PARAM_INT);
    if ($stmt_insert_invitation->execute()) {
        http_response_code(201); // Created
        echo json_encode(['message' => 'Pozvánka do skupiny byla úspěšně odeslána.']);
    } else {
        http_response_code(500); // Internal Server Error
        error_log("Error inserting group invitation: " . $stmt_insert_invitation->errorInfo()[2]);
        echo json_encode(['error' => 'Nepodařilo se odeslat pozvánku do skupiny. Zkuste to prosím znovu.']);
    }
} else {
     http_response_code(500); // Internal Server Error
     error_log("Error preparing insert group invitation statement: " . $pdo->errorInfo()[2]);
     echo json_encode(['error' => 'Interní chyba serveru.']);
}
unset($stmt_insert_invitation);

// Close connection
unset($pdo);
?>