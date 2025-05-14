<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: ../login.php");
    exit;
}

// Check if photo ID is provided
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    // Get ID from URL
    $photo_id = trim($_GET["id"]);

    // Prepare a select statement to get the filename
    $sql_select = "SELECT filename FROM photos WHERE id = :id";
    if ($stmt_select = $pdo->prepare($sql_select)) {
        // Bind parameters
        $stmt_select->bindParam(":id", $photo_id, PDO::PARAM_INT);

        // Attempt to execute the prepared statement
        if ($stmt_select->execute()) {
            if ($stmt_select->rowCount() == 1) {
                $row = $stmt_select->fetch(PDO::FETCH_ASSOC);
                $filename = $row['filename'];
                $file_path = "../uploads/" . $filename;

                // Delete the file from the uploads directory
                if (file_exists($file_path)) {
                    unlink($file_path);
                }

                // Prepare a delete statement
                $sql_delete = "DELETE FROM photos WHERE id = :id";
                if ($stmt_delete = $pdo->prepare($sql_delete)) {
                    // Bind parameters
                    $stmt_delete->bindParam(":id", $photo_id, PDO::PARAM_INT);

                    // Attempt to execute the prepared statement
                    if ($stmt_delete->execute()) {
                        // Redirect to dashboard
                        header("location: dashboard.php");
                        exit();
                    } else {
                        echo "Oops! Something went wrong with the database deletion. Please try again later.";
                    }
                }
                unset($stmt_delete);
            } else {
                echo "Error: Photo not found.";
            }
        } else {
            echo "Oops! Something went wrong with the select query. Please try again later.";
        }
    }
    unset($stmt_select);

    // Close connection
    unset($pdo);
} else {
    // If ID is not provided, redirect to dashboard
    header("location: dashboard.php");
    exit();
}
?>