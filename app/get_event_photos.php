<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in
if (!is_logged_in()) {
    http_response_code(401); // Unauthorized
    echo json_encode(["error" => "User not logged in."]);
    exit;
}

// Check if event_id is provided in the GET request
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Event ID not provided."]);
    exit;
}

$event_id = $_GET['event_id'];
$user_id = $_SESSION['id'];

// Check if the user is part of a group
$group_id = null;
$sql_check_group = "SELECT g.id FROM groups g
                    JOIN group_members gm ON g.id = gm.group_id
                    WHERE gm.user_id = :user_id
                    LIMIT 1";
if ($stmt_check_group = $pdo->prepare($sql_check_group)) {
    $stmt_check_group->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_group->execute()) {
        $group = $stmt_check_group->fetch(PDO::FETCH_ASSOC);
        if ($group) {
            $group_id = $group['id'];
        }
    }
    unset($stmt_check_group);
}

// If not in a group, check if in a pair (for backward compatibility)
$pair_id = null;
if ($group_id === null) {
    $sql_check_pair = "SELECT id FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
    if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
        $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        if ($stmt_check_pair->execute()) {
            $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
            if ($pair) {
                $pair_id = $pair['id'];
            }
        }
        unset($stmt_check_pair);
    }
}

// Fetch photos for the given event ID, ensuring the event belongs to the user's group or pair
$photos = [];
if ($group_id !== null) {
    // If user is in a group, fetch photos from events linked to that group
    $sql = "SELECT p.id, p.filename, p.description
            FROM photos p
            JOIN events e ON p.event_id = e.id
            WHERE p.event_id = :event_id AND e.group_id = :group_id
            ORDER BY p.id ASC";

    if ($stmt = $pdo->prepare($sql)) {
        $stmt->bindParam(":event_id", $event_id, PDO::PARAM_INT);
        $stmt->bindParam(":group_id", $group_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(["error" => "Error fetching photos."]);
            error_log("Error fetching photos for event ID " . $event_id . ": " . $stmt->errorInfo()[2]);
            exit;
        }
        unset($stmt);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Error preparing photo fetch query."]);
        exit;
    }
} elseif ($pair_id !== null) {
    // For backward compatibility - if user is in a pair, fetch photos from events linked to that pair
    $sql = "SELECT p.id, p.filename, p.description
            FROM photos p
            JOIN events e ON p.event_id = e.id
            WHERE p.event_id = :event_id AND e.pair_id = :pair_id
            ORDER BY p.id ASC";

    if ($stmt = $pdo->prepare($sql)) {
        $stmt->bindParam(":event_id", $event_id, PDO::PARAM_INT);
        $stmt->bindParam(":pair_id", $pair_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(["error" => "Error fetching photos."]);
            error_log("Error fetching photos for event ID " . $event_id . ": " . $stmt->errorInfo()[2]);
            exit;
        }
        unset($stmt);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Error preparing photo fetch query."]);
        exit;
    }
} else {
    // If not in a group or pair, they shouldn't be able to access event photos
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "User is not part of a group or pair."]);
    exit;
}


// Return photos as JSON
header('Content-Type: application/json');
echo json_encode($photos);

unset($pdo);
?>