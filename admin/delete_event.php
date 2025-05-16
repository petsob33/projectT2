<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in and is an admin (assuming admin check is part of auth.php or implied by access to admin folder)
if (!is_logged_in()) {
    header("location: ../public/login.php");
    exit;
}

// Check if event_id is provided in the GET request
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect back to dashboard if no event ID is provided
    header("location: ./dashboard.php");
    exit;
}

$event_id = $_GET['id'];
$user_id = $_SESSION['id'];

// Determine user's pair_id
$pair_id = null;
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

// Verify that the event belongs to the user's pair before deleting
if ($pair_id !== null) {
    $sql_check_event_pair = "SELECT id FROM events WHERE id = :event_id AND pair_id = :pair_id LIMIT 1";
    if ($stmt_check_event_pair = $pdo->prepare($sql_check_event_pair)) {
        $stmt_check_event_pair->bindParam(":event_id", $event_id, PDO::PARAM_INT);
        $stmt_check_event_pair->bindParam(":pair_id", $pair_id, PDO::PARAM_INT);

        if ($stmt_check_event_pair->execute() && $stmt_check_event_pair->rowCount() > 0) {
            // Event belongs to the pair, proceed with deletion

            // First, get the filenames of photos associated with the event
            $sql_get_photos = "SELECT filename FROM photos WHERE event_id = :event_id";
            $photo_filenames = [];
            if ($stmt_get_photos = $pdo->prepare($sql_get_photos)) {
                $stmt_get_photos->bindParam(":event_id", $event_id, PDO::PARAM_INT);
                if ($stmt_get_photos->execute()) {
                    $photo_filenames = $stmt_get_photos->fetchAll(PDO::FETCH_COLUMN);
                }
                unset($stmt_get_photos);
            }

            // Start a transaction
            $pdo->beginTransaction();

            try {
                // Delete photos from the database
                $sql_delete_photos = "DELETE FROM photos WHERE event_id = :event_id";
                if ($stmt_delete_photos = $pdo->prepare($sql_delete_photos)) {
                    $stmt_delete_photos->bindParam(":event_id", $event_id, PDO::PARAM_INT);
                    $stmt_delete_photos->execute();
                    unset($stmt_delete_photos);
                }

                // Delete the event from the database
                $sql_delete_event = "DELETE FROM events WHERE id = :event_id";
                if ($stmt_delete_event = $pdo->prepare($sql_delete_event)) {
                    $stmt_delete_event->bindParam(":event_id", $event_id, PDO::PARAM_INT);
                    $stmt_delete_event->execute();
                    unset($stmt_delete_event);
                }

                // If database deletions were successful, delete the actual photo files
                foreach ($photo_filenames as $filename) {
                    $file_path = "../uploads/" . $filename;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                // Commit the transaction
                $pdo->commit();

                // Redirect back to dashboard after successful deletion
                header("location: ./dashboard.php");
                exit;

            } catch (Exception $e) {
                // Rollback the transaction on error
                $pdo->rollBack();
                error_log("Error deleting event ID " . $event_id . ": " . $e->getMessage());
                echo "Chyba: Při mazání události došlo k problému. Zkuste to prosím znovu později.";
            }

        } else {
            // Event does not belong to the user's pair or does not exist
            echo "Chyba: Událost nenalezena nebo nemáte oprávnění ji smazat.";
        }
        unset($stmt_check_event_pair);
    } else {
        error_log("Error checking event pair status for deletion: " . $stmt_check_event_pair->errorInfo()[2]);
        echo "Chyba: Při ověřování události došlo k problému.";
    }
} else {
    // User is not part of a pair, cannot delete events linked to a pair
    echo "Chyba: Nemáte oprávnění mazat události.";
}


unset($pdo);
?>