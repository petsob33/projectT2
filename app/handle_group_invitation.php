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

// Check if invitation_id and action are provided in the POST request
if (!isset($_POST['invitation_id']) || empty(trim($_POST['invitation_id'])) || 
    !isset($_POST['action']) || empty(trim($_POST['action']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Chybí ID pozvánky nebo akce.']);
    exit;
}

$user_id = $_SESSION['id'];
$invitation_id = trim($_POST['invitation_id']);
$action = trim($_POST['action']); // 'accept' or 'reject'

// Validate action
if ($action !== 'accept' && $action !== 'reject') {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Neplatná akce.']);
    exit;
}

// Fetch the group invitation to verify it exists and the current user is the invitee
$invitation = null;
$sql_fetch_invitation = "SELECT id, group_id, inviter_id, invitee_id, status FROM group_invitations WHERE id = :id";
if ($stmt_fetch_invitation = $pdo->prepare($sql_fetch_invitation)) {
    $stmt_fetch_invitation->bindParam(":id", $invitation_id, PDO::PARAM_INT);
    if ($stmt_fetch_invitation->execute()) {
        $invitation = $stmt_fetch_invitation->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching group invitation: " . $stmt_fetch_invitation->errorInfo()[2]);
    }
}
unset($stmt_fetch_invitation);

if ($invitation === null) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Pozvánka do skupiny nebyla nalezena.']);
    exit;
}

// Check if the current user is the invitee of the invitation
if ($invitation['invitee_id'] != $user_id) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Nemáte oprávnění k provedení této akce.']);
    exit;
}

// Check if the invitation is still pending
if ($invitation['status'] !== 'pending') {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'Pozvánka do skupiny již byla zpracována.']);
    exit;
}

// Update the invitation status
$new_status = ($action === 'accept') ? 'accepted' : 'rejected';
$sql_update_invitation = "UPDATE group_invitations SET status = :status WHERE id = :id";
if ($stmt_update_invitation = $pdo->prepare($sql_update_invitation)) {
    $stmt_update_invitation->bindParam(":status", $new_status, PDO::PARAM_STR);
    $stmt_update_invitation->bindParam(":id", $invitation_id, PDO::PARAM_INT);
    if ($stmt_update_invitation->execute()) {

        if ($action === 'accept') {
            // If accepted, add the user to the group
            $group_id = $invitation['group_id'];
            
            // Check if the user is already a member of the group (shouldn't happen, but just in case)
            $is_already_member = false;
            $sql_check_membership = "SELECT id FROM group_members WHERE group_id = :group_id AND user_id = :user_id";
            if ($stmt_check_membership = $pdo->prepare($sql_check_membership)) {
                $stmt_check_membership->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                $stmt_check_membership->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                if ($stmt_check_membership->execute()) {
                    if ($stmt_check_membership->rowCount() > 0) {
                        $is_already_member = true;
                    }
                } else {
                    error_log("Error checking group membership: " . $stmt_check_membership->errorInfo()[2]);
                }
            }
            unset($stmt_check_membership);
            
            if ($is_already_member) {
                http_response_code(409); // Conflict
                echo json_encode(['error' => 'Již jste členem této skupiny.']);
                exit;
            }

            // Add the user to the group
            $sql_add_to_group = "INSERT INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)";
            if ($stmt_add_to_group = $pdo->prepare($sql_add_to_group)) {
                $stmt_add_to_group->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                $stmt_add_to_group->bindParam(":user_id", $user_id, PDO::PARAM_INT);

                if ($stmt_add_to_group->execute()) {
                    // Get the group name
                    $group_name = "Skupina";
                    $sql_get_group_name = "SELECT name FROM groups WHERE id = :group_id";
                    if ($stmt_get_group_name = $pdo->prepare($sql_get_group_name)) {
                        $stmt_get_group_name->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                        if ($stmt_get_group_name->execute()) {
                            $group = $stmt_get_group_name->fetch(PDO::FETCH_ASSOC);
                            if ($group) {
                                $group_name = $group['name'];
                            }
                        }
                        unset($stmt_get_group_name);
                    }
                    
                    http_response_code(200); // OK
                    echo json_encode(['message' => 'Pozvánka do skupiny byla přijata. Nyní jste členem skupiny "' . htmlspecialchars($group_name) . '"!']);
                } else {
                    // If adding to group fails, revert invitation status and log error
                    $sql_revert_invitation = "UPDATE group_invitations SET status = 'pending' WHERE id = :id";
                    $stmt_revert_invitation = $pdo->prepare($sql_revert_invitation);
                    $stmt_revert_invitation->bindParam(":id", $invitation_id, PDO::PARAM_INT);
                    $stmt_revert_invitation->execute(); // Attempt to revert, ignore errors here

                    http_response_code(500); // Internal Server Error
                    error_log("Error adding user to group: " . $stmt_add_to_group->errorInfo()[2]);
                    echo json_encode(['error' => 'Nepodařilo se přidat vás do skupiny. Zkuste to prosím znovu.']);
                }
                unset($stmt_add_to_group);
            } else {
                http_response_code(500); // Internal Server Error
                error_log("Error preparing add to group statement: " . $pdo->errorInfo()[2]);
                echo json_encode(['error' => 'Interní chyba serveru.']);
            }
        } else {
            // If rejected
            http_response_code(200); // OK
            echo json_encode(['message' => 'Pozvánka do skupiny byla odmítnuta.']);
        }

    } else {
        http_response_code(500); // Internal Server Error
        error_log("Error updating group invitation status: " . $stmt_update_invitation->errorInfo()[2]);
        echo json_encode(['error' => 'Nepodařilo se zpracovat pozvánku do skupiny. Zkuste to prosím znovu.']);
    }
} else {
    http_response_code(500); // Internal Server Error
    error_log("Error preparing update group invitation statement: " . $pdo->errorInfo()[2]);
    echo json_encode(['error' => 'Interní chyba serveru.']);
}
unset($stmt_update_invitation);

// Close connection
unset($pdo);
?>