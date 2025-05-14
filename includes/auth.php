<?php
// Prevent direct access to this file
define('INCLUDE_CHECK', true);

// Start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if a user is logged in
function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// Function to attempt login
function attempt_login($pdo, $username, $password) {
    $sql = "SELECT id, username, password_hash FROM users WHERE username = :username";

    if ($stmt = $pdo->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);

        // Set parameters
        $param_username = $username;

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Check if username exists, if yes then verify password
            if ($stmt->rowCount() == 1) {
                if ($row = $stmt->fetch()) {
                    $id = $row['id'];
                    $username = $row['username'];
                    $hashed_password = $row['password_hash'];
                    if (password_verify($password, $hashed_password)) {
                        // Password is correct, so start a new session
                        session_regenerate_id(true); // Regenerate session ID for security
                        $_SESSION['loggedin'] = true;
                        $_SESSION['id'] = $id;
                        $_SESSION['username'] = $username;
                        return true; // Login successful
                    } else {
                        return false; // Password is not valid
                    }
                }
            } else {
                return false; // No user with that username
            }
        } else {
            // Handle execution error
            error_log("Error executing login query: " . $stmt->errorInfo()[2]);
            return false;
        }
    }

    // Close statement
    unset($stmt);

    return false; // Something went wrong
}

// Function to log out the user
function logout() {
    // Unset all of the session variables
    $_SESSION = array();

    // Destroy the session.
    session_destroy();

    // Redirect to login page
    header("location: login.php");
    exit;
}
?>