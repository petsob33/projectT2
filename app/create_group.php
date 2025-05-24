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

// Check if group_name is provided in the POST request
if (!isset($_POST['group_name']) || empty(trim($_POST['group_name']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Není zadán název skupiny.']);
    exit;
}

$user_id = $_SESSION['id'];
$group_name = trim($_POST['group_name']);

// Get start_date from POST, validate and use current date if not provided or invalid
$start_date = null;
if (isset($_POST['start_date']) && !empty(trim($_POST['start_date']))) {
    $start_date_str = trim($_POST['start_date']);
    // Basic date validation (YYYY-MM-DD format)
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str)) {
        $start_date = $start_date_str;
    } else {
        error_log("Invalid start_date format received: " . $start_date_str);
    }
}

// Use current date if start_date is not provided or invalid
if ($start_date === null) {
    $start_date = date('Y-m-d');
    error_log("Using current date for start_date: " . $start_date);
}

// Create the new group
$sql_create_group = "INSERT INTO groups (name, start_date, created_by) VALUES (:name, :start_date, :created_by)";
if ($stmt_create_group = $pdo->prepare($sql_create_group)) {
    $stmt_create_group->bindParam(":name", $group_name, PDO::PARAM_STR);
    $stmt_create_group->bindParam(":start_date", $start_date, PDO::PARAM_STR);
    $stmt_create_group->bindParam(":created_by", $user_id, PDO::PARAM_INT);
    
    if ($stmt_create_group->execute()) {
        $group_id = $pdo->lastInsertId();
        
        // Add the creator as the first member of the group
        $sql_add_member = "INSERT INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)";
        if ($stmt_add_member = $pdo->prepare($sql_add_member)) {
            $stmt_add_member->bindParam(":group_id", $group_id, PDO::PARAM_INT);
            $stmt_add_member->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            
            if ($stmt_add_member->execute()) {
                http_response_code(201); // Created
                echo json_encode([
                    'message' => 'Skupina byla úspěšně vytvořena.',
                    'group_id' => $group_id,
                    'group_name' => $group_name
                ]);
            } else {
                // If adding member fails, delete the group and log error
                $sql_delete_group = "DELETE FROM groups WHERE id = :group_id";
                $stmt_delete_group = $pdo->prepare($sql_delete_group);
                $stmt_delete_group->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                $stmt_delete_group->execute(); // Attempt to delete, ignore errors here
                
                http_response_code(500); // Internal Server Error
                error_log("Error adding creator as group member: " . $stmt_add_member->errorInfo()[2]);
                echo json_encode(['error' => 'Nepodařilo se vytvořit skupinu. Zkuste to prosím znovu.']);
            }
            unset($stmt_add_member);
        } else {
            // If preparing add member statement fails, delete the group and log error
            $sql_delete_group = "DELETE FROM groups WHERE id = :group_id";
            $stmt_delete_group = $pdo->prepare($sql_delete_group);
            $stmt_delete_group->bindParam(":group_id", $group_id, PDO::PARAM_INT);
            $stmt_delete_group->execute(); // Attempt to delete, ignore errors here
            
            http_response_code(500); // Internal Server Error
            error_log("Error preparing add member statement: " . $pdo->errorInfo()[2]);
            echo json_encode(['error' => 'Interní chyba serveru.']);
        }
    } else {
        http_response_code(500); // Internal Server Error
        error_log("Error creating group: " . $stmt_create_group->errorInfo()[2]);
        echo json_encode(['error' => 'Nepodařilo se vytvořit skupinu. Zkuste to prosím znovu.']);
    }
    unset($stmt_create_group);
} else {
    http_response_code(500); // Internal Server Error
    error_log("Error preparing create group statement: " . $pdo->errorInfo()[2]);
    echo json_encode(['error' => 'Interní chyba serveru.']);
}

// Close connection
unset($pdo);
?>