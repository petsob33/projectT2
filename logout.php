<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include auth file
require_once "includes/auth.php";

// Call the logout function
logout();
?>