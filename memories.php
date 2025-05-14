<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "includes/db.php";
require_once "includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
error_log("DEBUG: Memories - User ID: " . $user_id);

// Check if the user is part of a pair
$pair_id = null;
$partner_id = null;
$partner_username = "Partner"; // Default value

error_log("DEBUG: Memories - Checking for pair for user ID: " . $user_id);
$sql_check_pair = "SELECT user1_id, user2_id, id FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_pair->execute()) {
        $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
        if ($pair) {
            $pair_id = $pair['id'];
            // Determine partner's ID
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
    } else {
        error_log("Error checking pair status for memories: " . $stmt_check_pair->errorInfo()[2]);
    }
}
unset($stmt_check_pair);

error_log("DEBUG: Memories - Pair ID: " . ($pair_id !== null ? $pair_id : "NULL"));
error_log("DEBUG: Memories - Partner ID: " . ($partner_id !== null ? $partner_id : "NULL"));

error_log("DEBUG: Memories - User ID before query: " . $user_id);
error_log("DEBUG: Memories - Pair ID before query: " . ($pair_id !== null ? $pair_id : "NULL"));
error_log("DEBUG: Memories - Partner ID before query: " . ($partner_id !== null ? $partner_id : "NULL"));

// Fetch photos ordered by date based on pairing status
$photos = [];

error_log("DEBUG: Memories - Before SQL: user_id=" . $user_id . ", pair_id=" . ($pair_id ?? 'NULL') . ", partner_id=" . ($partner_id ?? 'NULL'));
error_log("DEBUG: Memories - Before SQL: user_id=" . $user_id . ", pair_id=" . ($pair_id ?? 'NULL') . ", partner_id=" . ($partner_id ?? 'NULL'));

$sql = "SELECT id, filename, description, date FROM photos WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($pair_id !== null) {
    // Include photos from the pair and the partner
    $sql = "SELECT id, filename, description, date FROM photos WHERE user_id = :user_id OR user_id = :partner_id OR (user_id IS NULL AND pair_id = :pair_id)";
    $params[':partner_id'] = $partner_id;
    $params[':pair_id'] = $pair_id;
}

$sql .= " ORDER BY date DESC";

error_log("DEBUG: Memories - SQL Query: " . $sql);
error_log("DEBUG: Memories - SQL Params: " . json_encode($params));

if ($stmt = $pdo->prepare($sql)) {
    if ($stmt->execute($params)) {
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Handle error - log or display a message
        echo "Oops! Something went wrong. Please try again later.";
    }
}
unset($stmt);

// Close connection (if it was opened by db.php, though it's better to manage connection scope)
// unset($pdo); // Keep connection open if needed by auth.php or other includes

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naše vzpomínky - <?php echo $_SESSION['username'] . " a " . $partner_username; ?></title>
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
            <li class="mb-2"><a href="index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="memories.php" class="text-gray-700 hover:text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></a></li>
            <li class="mb-2"><a href="pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="admin/dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center p-4">
        <h1 class="text-4xl font-bold text-pastel-purple mb-8">Naše vzpomínky</h1>

        <div class="memories-list w-full max-w-sm">
            <?php if (!empty($photos)): ?>
                <?php foreach ($photos as $index => $photo): ?>
                    <div class="memory-item bg-white p-4 rounded-xl shadow-lg mb-4 w-full max-w-sm mx-auto"> <!-- Adjusted mb -->
                        <img src="uploads/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo htmlspecialchars($photo['description']); ?>" class="rounded-xl w-full h-auto mb-4">
                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($photo['date']); ?></p>
                        <?php if (!empty($photo['description'])): ?>
                            <p class="text-gray-700"><?php echo htmlspecialchars($photo['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($index < count($photos) - 1): ?>
                        <!-- Vertical separator below photo item -->
                        <div class="bg-black w-0.5 h-16 mx-auto my-4"></div> <!-- Vertical line below, centered -->
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-600">Zatím zde nejsou žádné vzpomínky.</p>
            <?php endif; ?>
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

   <script>
       // Log photo data to console for debugging
       const photosData = <?php echo json_encode($photos); ?>;
       console.log('Memories photo data:', photosData);
   </script>
</body>
</html>