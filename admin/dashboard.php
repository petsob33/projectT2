<?php
session_start();
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: ../public/login.php");
    exit;
}

// Get user information
$user_id = $_SESSION['id'];
$group_id = null;
$group_name = null;
$pair_id = null;
$partner_username = "Partner"; // Default value
$relationship_start_date = null;
$error_message = "";
$is_group = false;

// Check if the user is part of a group
$sql_check_group = "SELECT g.id, g.name, g.start_date FROM groups g
                    JOIN group_members gm ON g.id = gm.group_id
                    WHERE gm.user_id = :user_id
                    LIMIT 1";
if ($stmt_check_group = $pdo->prepare($sql_check_group)) {
    $stmt_check_group->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_group->execute()) {
        $group = $stmt_check_group->fetch(PDO::FETCH_ASSOC);
        if ($group) {
            $group_id = $group['id'];
            $group_name = htmlspecialchars($group['name']);
            $relationship_start_date = $group['start_date'];
            $is_group = true;
        }
    } else {
        error_log("Error checking group status for admin dashboard: " . $stmt_check_group->errorInfo()[2]);
    }
}
unset($stmt_check_group);

// If not in a group, check if in a pair (for backward compatibility)
if ($group_id === null) {
    // Check if the user is part of a pair and get pair_id and start_date
    $sql_get_pair_info = "SELECT p.id, p.start_date, u.username AS partner_username
                          FROM pairs p
                          JOIN users u ON (u.id = p.user1_id OR u.id = p.user2_id) AND u.id != :user_id
                          WHERE p.user1_id = :user_id OR p.user2_id = :user_id
                          LIMIT 1";
    if ($stmt_get_pair_info = $pdo->prepare($sql_get_pair_info)) {
        $stmt_get_pair_info->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        if ($stmt_get_pair_info->execute()) {
            $pair = $stmt_get_pair_info->fetch(PDO::FETCH_ASSOC);
            if ($pair) {
                $pair_id = $pair['id'];
                $relationship_start_date = $pair['start_date'];
                $partner_username = htmlspecialchars($pair['partner_username']);
            }
        } else {
            error_log("Error fetching pair info for admin dashboard: " . $stmt_get_pair_info->errorInfo()[2]);
        }
    }
    unset($stmt_get_pair_info);
}


// Handle form submission for setting start date
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_date'])) {
    $submitted_date = trim($_POST['start_date']);

    // Validate date format (basic YYYY-MM-DD)
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $submitted_date)) {
        if ($group_id !== null) {
            // Update the start_date for the group
            $sql_update_start_date = "UPDATE groups SET start_date = :start_date WHERE id = :group_id";
            if ($stmt_update_start_date = $pdo->prepare($sql_update_start_date)) {
                $stmt_update_start_date->bindParam(":start_date", $submitted_date, PDO::PARAM_STR);
                $stmt_update_start_date->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                if ($stmt_update_start_date->execute()) {
                    // Redirect to prevent form resubmission and show updated duration
                    header("location: ./dashboard.php");
                    exit();
                } else {
                    $error_message = "Chyba při ukládání data začátku skupiny.";
                    error_log("Error updating start_date in admin dashboard: " . $stmt_update_start_date->errorInfo()[2]);
                }
                unset($stmt_update_start_date);
            } else {
                $error_message = "Interní chyba serveru při přípravě aktualizace data.";
                error_log("Error preparing update start_date statement in admin dashboard: " . $pdo->errorInfo()[2]);
            }
        } else if ($pair_id !== null) {
            // Update the start_date for the pair
            $sql_update_start_date = "UPDATE pairs SET start_date = :start_date WHERE id = :pair_id";
            if ($stmt_update_start_date = $pdo->prepare($sql_update_start_date)) {
                $stmt_update_start_date->bindParam(":start_date", $submitted_date, PDO::PARAM_STR);
                $stmt_update_start_date->bindParam(":pair_id", $pair_id, PDO::PARAM_INT);
                if ($stmt_update_start_date->execute()) {
                    // Redirect to prevent form resubmission and show updated duration
                    header("location: ./dashboard.php");
                    exit();
                } else {
                    $error_message = "Chyba při ukládání data začátku vztahu.";
                    error_log("Error updating start_date in admin dashboard: " . $stmt_update_start_date->errorInfo()[2]);
                }
                unset($stmt_update_start_date);
            } else {
                $error_message = "Interní chyba serveru při přípravě aktualizace data.";
                error_log("Error preparing update start_date statement in admin dashboard: " . $pdo->errorInfo()[2]);
            }
        } else {
            $error_message = "Nejste součástí žádné skupiny ani páru. Nelze nastavit datum začátku.";
        }
    } else {
        $error_message = "Neplatný formát data. Použijte prosím formát RRRR-MM-DD.";
    }
}


// Fetch events and their associated photos ordered by event date
$events = [];

// Fetch events and their associated photos
if ($group_id !== null) {
    // If user is in a group, fetch events linked to that group
    $sql = "SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.date AS event_date,
                p.id AS photo_id,
                p.filename,
                p.description
            FROM events e
            JOIN photos p ON e.id = p.event_id
            WHERE e.group_id = :group_id
            ORDER BY e.date DESC, p.id ASC"; // Order by event date descending, then photo ID ascending

    $params = [':group_id' => $group_id];
} elseif ($pair_id !== null) {
    // For backward compatibility - if user is in a pair, fetch events linked to that pair
    $sql = "SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.date AS event_date,
                p.id AS photo_id,
                p.filename,
                p.description
            FROM events e
            JOIN photos p ON e.id = p.event_id
            WHERE e.pair_id = :pair_id
            ORDER BY e.date DESC, p.id ASC"; // Order by event date descending, then photo ID ascending

    $params = [':pair_id' => $pair_id];
} else {
    $sql = ""; // No query if not in a group or pair
    $params = [];
}

    if ($stmt = $pdo->prepare($sql)) {
        if ($stmt->execute($params)) {
            $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group photos by event
            foreach ($raw_data as $row) {
                $event_id = $row['event_id'];
                if (!isset($events[$event_id])) {
                    $events[$event_id] = [
                        'id' => $event_id,
                        'name' => htmlspecialchars($row['event_name']),
                        'date' => htmlspecialchars($row['event_date']),
                        'photos' => []
                    ];
                }
                $events[$event_id]['photos'][] = [
                    'id' => $row['photo_id'],
                    'filename' => htmlspecialchars($row['filename']),
                    'description' => htmlspecialchars($row['description'])
                ];
            }
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
    <title>Admin Dashboard - Naše fotky</title>
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
        <div class="text-3xl font-bold text-pastel-purple">Admin Dashboard</div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
<!-- Light/Dark Mode Toggle -->
<div class="flex items-center mb-4">
    <label for="mode-toggle" class="mr-2 text-pastel-purple">Light/Dark Mode:</label>
    <input type="checkbox" id="mode-toggle" class="toggle-checkbox" />
</div>
<script>
    // Check for saved mode preference
    const modeToggle = document.getElementById('mode-toggle');
    const currentMode = '<?php echo $_SESSION["mode"] ?? "light"; ?>';
    document.body.classList.toggle('dark', currentMode === 'dark');
    modeToggle.checked = currentMode === 'dark';

    modeToggle.addEventListener('change', () => {
        const newMode = modeToggle.checked ? 'dark' : 'light';
        fetch('../set_mode.php', {
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
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="../app/pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="../app/group_invitations.php" class="text-gray-700 hover:text-pastel-purple">Skupiny a pozvánky</a></li>
            <li class="mb-2"><a href="../public/relationship_duration.php" class="text-gray-700 hover:text-pastel-purple">Délka vztahu</a></li>
            <li class="mb-2"><a href="./dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="../public/logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center p-4">
        <div class="admin-dashboard-content bg-white p-6 rounded-xl shadow-lg w-full max-w-4xl">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Admin Dashboard</h1>
            <p class="text-gray-700 mb-6">Vítejte, <?php echo htmlspecialchars($_SESSION["username"]); ?>.</p>

            <!-- Relationship Start Date Form -->
            <div class="mb-8 pb-8 border-b border-gray-200">
                <h2 class="text-3xl font-bold text-pastel-purple mb-4">
                    <?php echo $is_group ? "Datum založení skupiny" : "Datum začátku vztahu"; ?>
                </h2>
                 <?php if (!empty($error_message)): ?>
                     <div class="text-red-500 text-sm mb-4"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                <form action="./dashboard.php" method="post" class="space-y-4">
                    <div>
                        <label for="start_date" class="block text-gray-700 font-bold mb-2">Datum začátku</label>
                        <input type="date" id="start_date" name="start_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo htmlspecialchars($relationship_start_date); ?>" required>
                    </div>
                    <button type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer">Uložit datum</button>
                </form>
            </div>


            <div class="mb-6">
                <a href="./upload.php" class="inline-block bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300">Nahrát novou událost s fotkami</a>
            </div>

            <h2 class="text-3xl font-bold text-pastel-purple mb-4">Spravovat události</h2>
            <div class="event-list overflow-x-auto">
                <?php if (!empty($events)): ?>
                    <table class="min-w-full bg-white rounded-xl overflow-hidden">
                        <thead>
                            <tr class="bg-pastel-pink text-pastel-purple uppercase text-base leading-normal">
                                <th class="py-3 px-6 text-left">Název události</th>
                                <th class="py-3 px-6 text-left">Datum</th>
                                <th class="py-3 px-6 text-left">Počet fotek</th>
                                <th class="py-3 px-6 text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-base font-light">
                            <?php foreach ($events as $event): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo $event['name']; ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo $event['date']; ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo count($event['photos']); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <a href="../app/memories.php#event-<?php echo $event['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">Zobrazit</a>
                                        <!-- Add delete event functionality later if needed -->
                                        <a href="./delete_event.php?id=<?php echo $event['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Opravdu chcete smazat tuto událost a všechny její fotky?');">Smazat</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-600">Zatím zde nejsou žádné události s fotkami.</p>
                <?php endif; ?>
            </div>
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
       // Log event data to console for debugging
       const eventsData = <?php echo json_encode($events); ?>;
       console.log('Admin Dashboard event data:', eventsData);
   </script>
</body>
</html>