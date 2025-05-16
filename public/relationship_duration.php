<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: ./login.php");
    exit;
}

$user_id = $_SESSION['id'];
$relationship_start_date = null;
$partner_username = "Partner"; // Default value
$relationship_start_date = null;
$partner_username = "Partner"; // Default value

// Fetch the pair's start date and partner's username
$sql_get_pair_info = "SELECT p.start_date, u.username AS partner_username
                      FROM pairs p
                      JOIN users u ON (u.id = p.user1_id OR u.id = p.user2_id) AND u.id != :user_id
                      WHERE p.user1_id = :user_id OR p.user2_id = :user_id
                      LIMIT 1";

if ($stmt_get_pair_info = $pdo->prepare($sql_get_pair_info)) {
    $stmt_get_pair_info->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_get_pair_info->execute()) {
        $pair_info = $stmt_get_pair_info->fetch(PDO::FETCH_ASSOC);
        if ($pair_info) {
            $relationship_start_date = $pair_info['start_date'];
            $partner_username = htmlspecialchars($pair_info['partner_username']);
        }
    } else {
        error_log("Error fetching pair info for relationship duration: " . $stmt_get_pair_info->errorInfo()[2]);
    }
}
unset($stmt_get_pair_info);

// Calculate duration if start date is available
$duration_message = "Datum začátku vztahu není nastaveno.";
if ($relationship_start_date) {
    $start_date_obj = new DateTime($relationship_start_date);
    $current_date_obj = new DateTime();
    $interval = $start_date_obj->diff($current_date_obj);

    $years = $interval->y;
    $months = $interval->m;
    $days = $interval->d;

    $duration_parts = [];
    if ($years > 0) {
        $duration_parts[] = $years . " let";
    }
    if ($months > 0) {
        $duration_parts[] = $months . " měsíců";
    }
    if ($days > 0) {
        $duration_parts[] = $days . " dní";
    }

    if (!empty($duration_parts)) {
        $duration_message = "Jste spolu " . implode(", ", $duration_parts) . ".";
    } else {
        $duration_message = "Jste spolu méně než jeden den.";
    }
}

// Close connection
unset($pdo);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Délka vztahu - Naše fotky</title>
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
<body class="bg-pastel-blue min-h-screen flex flex-col text-base">
    <header class="bg-pastel-pink p-4 flex justify-between items-center shadow-md">
        <div class="text-3xl font-bold text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="./index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="../app/pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
             <li class="mb-2"><a href="./relationship_duration.php" class="text-gray-700 hover:text-pastel-purple">Délka vztahu</a></li>
            <li class="mb-2"><a href="../admin/dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="./logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center justify-center p-4">
        <div class="relationship-duration-content bg-white p-6 rounded-xl shadow-lg w-full max-w-md text-center">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Délka vztahu</h1>
            <p class="text-gray-700 text-xl mb-4"><?php echo $duration_message; ?></p>

        </div>
    </main>

    <script>
        // Basic JavaScript for hamburger menu toggle
        const hamburgerIcon = document.querySelector('.hamburger-menu-icon');
        const sidebarMenu = document.querySelector('.sidebar-menu');
        const overlay = document.querySelector('.overlay');

        hamburgerIcon.addEventListener('click', () => {
            sidebarMenu.classList.toggle('open');
            overlay.classList.toggle('opacity-0');
            overlay.classList.toggle('pointer-events-none');
        });

        overlay.addEventListener('click', () => {
            sidebarMenu.classList.remove('open');
            overlay.classList.add('opacity-0');
            overlay.classList.add('pointer-events-none');
        });
    </script>
</body>
</html>