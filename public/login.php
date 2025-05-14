<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "includes/db.php";
require_once "includes/auth.php";

// Check if the user is already logged in, if yes then redirect to dashboard
if (is_logged_in()) {
    header("location: admin/dashboard.php");
    exit;
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Prosím, zadejte uživatelské jméno.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Prosím, zadejte heslo.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        if (attempt_login($pdo, $username, $password)) {
            // Redirect user to admin dashboard page
            header("location: admin/dashboard.php");
            exit(); // Added exit after header
        } else {
            // Password is not valid, display a generic error message
            $login_err = "Neplatné uživatelské jméno nebo heslo.";
        }
    }

    // Close connection
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Naše fotky</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style type="text/tailwindcss">
        @layer utilities {
            .bg-pastel-pink {
                background-color: #F8F8F8; /* Off-White */
            }
            .bg-pastel-blue {
                background-color: #F8F8F8; /* Off-White */
            }
            .text-pastel-purple {
                color: #DC143C; /* Cherry Red */
            }
            .rounded-xl {
                border-radius: 1rem;
            }
            /* Add more custom styles for romantic/elegant look */
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .sidebar-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-menu.open {
            transform: translateX(0);
        }
    </style>
</head>
<body class="bg-pastel-blue min-h-screen flex flex-col items-center justify-center p-4 text-base">
    <!-- Header and Sidebar removed for login page simplicity -->

    <div class="login-form-container bg-white p-8 rounded-xl shadow-lg w-full max-w-sm text-center">
        <h1 class="text-4xl font-bold text-pastel-purple mb-6">Přihlášení</h1>
        <p class="text-gray-700 text-lg mb-6">Prosím, vyplňte své přihlašovací údaje.</p>

        <?php
        if (!empty($login_err)) {
            echo '<div class="text-red-500 text-sm mb-4">' . $login_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 font-bold mb-2 text-left">Uživatelské jméno</label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $username; ?>">
                <?php if (!empty($username_err)): ?><p class="text-red-500 text-xs italic text-left"><?php echo $username_err; ?></p><?php endif; ?>
            </div>
            <div>
                <label for="password" class="block text-gray-700 font-bold mb-2 text-left">Heslo</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>">
                <?php if (!empty($password_err)): ?><p class="text-red-500 text-xs italic text-left"><?php echo $password_err; ?></p><?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <input type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer" value="Přihlásit se">
            </div>
        </form>
        <p class="text-gray-600 text-sm mt-6">Nemáte účet? <a href="register.php" class="text-pastel-purple hover:underline">Zaregistrujte se</a>.</p>
    </div>

</body>
</html>