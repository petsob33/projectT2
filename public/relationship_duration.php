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
$start_date = null;
$partner_username = "Partner"; // Default value

// Check if the user is part of a pair and get the start date and partner's ID
$sql_check_pair = "SELECT user1_id, user2_id, start_date FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_pair->execute()) {
        $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
        if ($pair) {
            $start_date = $pair['start_date'];
            $partner_id = ($pair['user1_id'] == $user_id) ? $pair['user2_id'] : $pair['user1_id'];

            // Fetch partner's username
            $sql_get_partner_name = "SELECT username FROM users WHERE id = :partner_id LIMIT 1";
            if ($stmt_get_partner_name = $pdo->prepare($sql_get_partner_name)) {
                $stmt_get_partner_name->bindParam(":partner_id", $partner_id, PDO::PARAM_INT);
                if ($stmt_get_partner_name->execute()) {
                    $partner = $stmt_get_partner_name->fetch(PDO::FETCH_ASSOC);
                    if ($partner) {
                        $partner_username = htmlspecialchars($partner['username']);
                    }
                }
                unset($stmt_get_partner_name);
            }
        }
    }
    unset($stmt_check_pair);
}

// Calculate duration if start date is available
$duration_message = "Datum začátku vztahu není nastaveno.";
if ($start_date) {
    $start_datetime = new DateTime($start_date);
    $current_datetime = new DateTime();
    $interval = $start_datetime->diff($current_datetime);

    $years = $interval->y;
    $months = $interval->m;
    $days = $interval->d;

    $duration_parts = [];
    if ($years > 0) {
        $duration_parts[] = $years . " " . ($years === 1 ? "rok" : ($years >= 2 && $years <= 4 ? "roky" : "let"));
    }
    if ($months > 0) {
        $duration_parts[] = $months . " " . ($months === 1 ? "měsíc" : ($months >= 2 && $months <= 4 ? "měsíce" : "měsíců"));
    }
    if ($days > 0) {
        $duration_parts[] = $days . " " . ($days === 1 ? "den" : "dní");
    }

    if (!empty($duration_parts)) {
        $duration_message = "Jste spolu: " . implode(", ", $duration_parts);
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
    <title>Délka vztahu - <?php echo $_SESSION['username'] . " a " . $partner_username; ?></title>
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
<body class="bg-gray-100 min-h-screen flex flex-col text-base">
    <header class="bg-pastel-pink p-4 flex justify-between items-center shadow-md">
        <div class="text-3xl font-bold text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="./index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></a></li>
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

    <main class="flex-grow flex flex-col items-center p-4">
        <div class="relationship-duration-content bg-white p-6 rounded-xl shadow-lg w-full max-w-md text-center">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Délka vztahu</h1>
            <p class="text-gray-700 text-xl"><?php echo htmlspecialchars($duration_message); ?></p>
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