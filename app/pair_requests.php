<?php
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

$user_id = $_SESSION['id'];
$pair_id = null;
$partner_id = null;
$partner_username = "Partner"; // Default value

// Check if the user is part of a pair and get partner's ID
$sql_check_pair = "SELECT user1_id, user2_id FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_check_pair->execute()) {
        $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
        if ($pair) {
            $pair_id = $pair['id']; // Although not used here, might be useful later
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

// Fetch received pair requests
$received_requests = [];
$sql_received = "SELECT pr.id, u.username AS requester_username, pr.created_at
                 FROM pair_requests pr
                 JOIN users u ON u.id = pr.requester_id
                 WHERE pr.recipient_id = :user_id AND pr.status = 'pending'
                 ORDER BY pr.created_at DESC";
if ($stmt_received = $pdo->prepare($sql_received)) {
    $stmt_received->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_received->execute()) {
        $received_requests = $stmt_received->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching received pair requests: " . $stmt_received->errorInfo()[2]);
    }
}
unset($stmt_received);

// Fetch sent pair requests
$sent_requests = [];
$sql_sent = "SELECT pr.id, u.username AS recipient_username, pr.status, pr.created_at
             FROM pair_requests pr
             JOIN users u ON u.id = pr.recipient_id
             WHERE pr.requester_id = :user_id
             ORDER BY pr.created_at DESC";
if ($stmt_sent = $pdo->prepare($sql_sent)) {
    $stmt_sent->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_sent->execute()) {
        $sent_requests = $stmt_sent->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching sent pair requests: " . $stmt_sent->errorInfo()[2]);
    }
}
unset($stmt_sent);

// Close connection
unset($pdo);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Žádosti o párování - <?php echo $_SESSION['username'] . " a " . $partner_username; ?></title>
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
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="./memories.php" class="text-gray-700 hover:text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></a></li>
            <li class="mb-2"><a href="./pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
             <li class="mb-2"><a href="../public/relationship_duration.php" class="text-gray-700 hover:text-pastel-purple">Délka vztahu</a></li>
            <li class="mb-2"><a href="../admin/dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="../public/logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center p-4">
        <div class="pair-requests-content bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Žádosti o párování</h1>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-pastel-purple mb-4">Přijaté žádosti</h2>
                <?php if (!empty($received_requests)): ?>
                    <ul class="space-y-4">
                        <?php foreach ($received_requests as $request): ?>
                            <li class="border-b border-gray-200 pb-4">
                                <p class="text-gray-700 mb-2">Žádost od: <span class="font-semibold"><?php echo htmlspecialchars($request['requester_username']); ?></span> (odesláno: <?php echo htmlspecialchars($request['created_at']); ?>)</p>
                                <div class="flex space-x-4">
                                    <button class="bg-green-500 text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition duration-300" onclick="handleRequest(<?php echo $request['id']; ?>, 'accept')">Přijmout</button>
                                    <button class="bg-red-500 text-white font-bold py-2 px-4 rounded hover:bg-red-600 transition duration-300" onclick="handleRequest(<?php echo $request['id']; ?>, 'reject')">Odmítnout</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray-600">Nemáte žádné nové žádosti o párování.</p>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="text-2xl font-bold text-pastel-purple mb-4">Odeslané žádosti</h2>
                <?php if (!empty($sent_requests)): ?>
                    <ul class="space-y-4">
                        <?php foreach ($sent_requests as $request): ?>
                            <li class="border-b border-gray-200 pb-4">
                                <p class="text-gray-700 mb-2">Žádost pro: <span class="font-semibold"><?php echo htmlspecialchars($request['recipient_username']); ?></span> (stav: <?php echo htmlspecialchars($request['status']); ?>, odesláno: <?php echo htmlspecialchars($request['created_at']); ?>)</p>
                                <!-- Optionally add a cancel button for pending requests -->
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray-600">Nemáte žádné odeslané žádosti o párování.</p>
                <?php endif; ?>
            </div>

            <!-- Form to send a new pair request -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                 <h2 class="text-2xl font-bold text-pastel-purple mb-4">Odeslat novou žádost</h2>
                 <form id="send-request-form" class="space-y-4">
                    <div>
                        <label for="recipient_username" class="block text-gray-700 font-bold mb-2 text-left">Uživatelské jméno příjemce</label>
                        <input type="text" id="recipient_username" name="recipient_username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <button type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300">Odeslat žádost</button>
                 </form>
                 <div id="send-request-message" class="mt-4 text-sm"></div>
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

        // JavaScript for handling pair requests
        async function handleRequest(requestId, action) {
            const response = await fetch('./handle_pair_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `request_id=${requestId}&action=${action}`
            });
            const result = await response.json();
            alert(result.message || result.error);
            if (response.ok) {
                // Reload the page to update the list
                window.location.reload();
            }
        }

        // JavaScript for sending pair requests
        document.getElementById('send-request-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const recipientUsername = document.getElementById('recipient_username').value;
            const messageDiv = document.getElementById('send-request-message');
            messageDiv.textContent = ''; // Clear previous messages

            const response = await fetch('./send_pair_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `recipient_username=${encodeURIComponent(recipientUsername)}`
            });
            const result = await response.json();
            messageDiv.textContent = result.message || result.error;
            messageDiv.style.color = response.ok ? 'green' : 'red';

            if (response.ok) {
                 // Optionally clear the input field
                 document.getElementById('recipient_username').value = '';
                 // Reload sent requests section or page
                 setTimeout(() => window.location.reload(), 2000); // Reload after a short delay
            }
        });

    </script>
</body>
</html>