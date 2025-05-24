<?php
session_start();
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Define variables and initialize with empty values
$username = $password = $confirm_password = "";
$username_err = $password_err = $confirm_password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Prosím, zadejte uživatelské jméno.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = :username";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);

            // Set parameters
            $param_username = trim($_POST["username"]);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $username_err = "Toto uživatelské jméno je již obsazeno.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Něco se pokazilo. Zkuste to prosím znovu později.";
            }

            // Close statement
            unset($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Prosím, zadejte heslo.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Heslo musí mít alespoň 6 znaků.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Prosím, potvrďte heslo.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Hesla se neshodují.";
        }
    }

    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":password_hash", $param_password_hash, PDO::PARAM_STR);

            // Set parameters
            $param_username = $username;
            $param_password_hash = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Redirect to login page
                header("location: ./login.php");
                exit(); // Added exit after header
            } else {
                echo "Oops! Něco se pokazilo. Zkuste to prosím znovu později.";
            }

            // Close statement
            unset($stmt);
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
    <title>Registrace - Naše fotky</title>
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
    <!-- Header and Sidebar removed for registration page simplicity -->

    <div class="register-form-container bg-white p-8 rounded-xl shadow-lg w-full max-w-sm text-center">
        <h1 class="text-4xl font-bold text-pastel-purple mb-6">Registrace</h1>
        <p class="text-gray-700 text-lg mb-6">Vyplňte prosím tento formulář pro vytvoření účtu.</p>

        <?php
        if (!empty($username_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $username_err . '</div>'; }
        if (!empty($password_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $password_err . '</div>'; }
        if (!empty($confirm_password_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $confirm_password_err . '</div>'; }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 font-bold mb-2 text-left">Uživatelské jméno</label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $username; ?>">
                <?php if (!empty($username_err)): ?><p class="text-red-500 text-xs italic text-left"><?php echo $username_err; ?></p><?php endif; ?>
            </div>
            <div>
                <label for="password" class="block text-gray-700 font-bold mb-2 text-left">Heslo</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $password; ?>">
                <?php if (!empty($password_err)): ?><p class="text-red-500 text-xs italic text-left"><?php echo $password_err; ?></p><?php endif; ?>
            </div>
            <div>
                <label for="confirm_password" class="block text-gray-700 font-bold mb-2 text-left">Potvrdit heslo</label>
                <input type="password" id="confirm_password" name="confirm_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>">
                <?php if (!empty($confirm_password_err)): ?><p class="text-red-500 text-xs italic text-left"><?php echo $confirm_password_err; ?></p><?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <input type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer" value="Zaregistrovat se">
            </div>
        </form>
        <p class="text-gray-600 text-sm mt-6">Již máte účet? <a href="./login.php" class="text-pastel-purple hover:underline">Přihlaste se zde</a>.</p>
    </div>

</body>
</html>
<script>
    // Check for saved mode preference
    const modeToggle = document.getElementById('mode-toggle');
    const currentMode = '<?php echo $_SESSION["mode"] ?? "light"; ?>';
    document.body.classList.toggle('dark', currentMode === 'dark');
    modeToggle.checked = currentMode === 'dark';

    modeToggle.addEventListener('change', () => {
        const newMode = modeToggle.checked ? 'dark' : 'light';
        fetch('./set_mode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ mode: newMode })
        }).then(() => {
            document.body.classList.toggle('dark', newMode === 'dark');
        });
    });
</script>
<style>
    body {
        transition: background-color 0.3s, color 0.3s;
    }
    body.dark {
        background-color: #1a1a1a; /* Dark background */
        color: #f0f0f0; /* Light text */
    }
</style>