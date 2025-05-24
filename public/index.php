<?php
session_start();
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

// Check if the user is part of a group
$group_id = null;
$group_name = null;
$sql_check_group = "SELECT g.id, g.name FROM groups g
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
        }
    }
    unset($stmt_check_group);
}

// If not in a group, check if in a pair (for backward compatibility)
$pair_id = null;
$partner_username = "Partner"; // Default value
if ($group_id === null) {
    $sql_check_pair = "SELECT p.id, u.username AS partner_username
                       FROM pairs p
                       JOIN users u ON (u.id = p.user1_id OR u.id = p.user2_id) AND u.id != :user_id
                       WHERE p.user1_id = :user_id OR p.user2_id = :user_id
                       LIMIT 1";
    if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
        $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        if ($stmt_check_pair->execute()) {
            $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
            if ($pair) {
                $pair_id = $pair['id'];
                $partner_username = htmlspecialchars($pair['partner_username']);
            }
        }
        unset($stmt_check_pair);
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
    <title>Naše fotky - <?php echo $group_id !== null ? $group_name : $_SESSION['username'] . " a " . $partner_username; ?></title>
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
        <div class="text-3xl font-bold text-pastel-purple">
            <?php
            if ($group_id !== null) {
                echo htmlspecialchars($group_name);
            } else {
                echo $_SESSION['username'] . " a " . $partner_username;
            }
            ?>
        </div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="./index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="../app/pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="../app/group_invitations.php" class="text-gray-700 hover:text-pastel-purple">Skupiny a pozvánky</a></li>
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
        <div class="photo-display bg-white p-4 rounded-xl shadow-lg max-w-xl w-full text-center">
            <img id="photo" src="" alt="Aktuální fotka" class="rounded-xl w-full h-auto mb-4">
            <div class="photo-info">
                <h2 id="event-name" class="text-lg font-semibold text-gray-800 mb-2"></h2>
                <!-- Optional: Add elements for description and date if needed -->
                <!-- <p id="photo-description" class="text-gray-700"></p>
                <p id="photo-date" class="text-sm text-gray-600"></p> -->
            </div>
        </div>
        <button id="next-photo-btn" class="mt-6 bg-pastel-pink text-pastel-purple font-bold py-3 px-6 rounded-xl shadow-lg hover:opacity-90 transition duration-300">
            Zobrazit další fotku
        </button>
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

        // JavaScript for fetching and displaying photos with animation
        const photoElement = document.getElementById('photo');
        const eventNameElement = document.getElementById('event-name'); // Get the new element
        const nextPhotoButton = document.getElementById('next-photo-btn');

        // Function to fetch a random photo
        async function fetchRandomPhoto() {
            try {
                const response = await fetch('../app/get_random_photo.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const photoData = await response.json();
                console.log('Random photo data:', photoData);

                // Apply fade-out animation
                photoElement.style.opacity = 0;
                eventNameElement.style.opacity = 0; // Fade out event name too

                // Wait for animation to complete (adjust time as needed)
                setTimeout(() => {
                    // Update photo source
                    photoElement.src = photoData.filename ? '../uploads/' + photoData.filename : ''; // Handle case with no photo
                    // Update event name
                    eventNameElement.textContent = photoData.event_name ? 'Událost: ' + photoData.event_name : ''; // Display event name or empty

                    // Apply fade-in animation
                    photoElement.style.opacity = 1;
                    eventNameElement.style.opacity = 1; // Fade in event name
                }, 300); // Match this duration with CSS transition duration
            } catch (error) {
                console.error('Error fetching random photo:', error);
                // Optionally display an error message to the user
                photoElement.src = ''; // Clear photo on error
                eventNameElement.textContent = 'Chyba při načítání fotky.'; // Display error message
                photoElement.style.opacity = 1; // Ensure elements are visible to show error
                eventNameElement.style.opacity = 1;
            }
        }

        // Add event listener to the button
        nextPhotoButton.addEventListener('click', fetchRandomPhoto);

        // Fetch initial photo when the page loads
        fetchRandomPhoto();

    </script>
    <style>
        /* Add CSS for fade transition */
        #photo, #event-name {
            transition: opacity 0.3s ease-in-out;
        }
    </style>
</body>
</html>
<script>
    // Check for saved mode preference
    const currentMode = '<?php echo $_SESSION["mode"] ?? "light"; ?>';
    document.body.classList.toggle('dark', currentMode === 'dark');
    
    // Add mode toggle to the sidebar
    const sidebarMenu = document.querySelector('.sidebar-menu ul');
    if (sidebarMenu) {
        const modeToggleItem = document.createElement('li');
        modeToggleItem.className = 'mb-2 mt-4';
        modeToggleItem.innerHTML = `
            <div class="flex items-center">
                <span class="text-gray-700 mr-2">Tmavý režim</span>
                <label class="switch">
                    <input type="checkbox" id="mode-toggle" ${currentMode === 'dark' ? 'checked' : ''}>
                    <span class="slider round"></span>
                </label>
            </div>
        `;
        sidebarMenu.appendChild(modeToggleItem);
        
        // Now add the event listener after the element exists
        const modeToggle = document.getElementById('mode-toggle');
        if (modeToggle) {
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
        }
    }
</script>
<style>
    body {
        transition: background-color 0.3s, color 0.3s;
    }
    body.dark {
        background-color: #1a1a1a; /* Dark background */
        color: #f0f0f0; /* Light text */
    }
    /* Style for the toggle switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 20px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #DC143C;
    }
    input:checked + .slider:before {
        transform: translateX(20px);
    }
    .slider.round {
        border-radius: 20px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
</style>