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

// No need to fetch all photos here, JavaScript will fetch one randomly
// No need for session photo index or next photo button logic here

// Close connection (if it was opened by db.php, though it's better to manage connection scope)
// unset($pdo); // Keep connection open if needed by auth.php or other includes

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naše fotky - <?php echo $_SESSION['username'] . " a " . $partner_username; ?></title>
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
            <li class="mb-2"><a href="index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="admin/dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center justify-center p-4">
        <div class="photo-display bg-white p-4 rounded-xl shadow-lg max-w-xl w-full text-center">
            <img id="photo" src="" alt="Aktuální fotka" class="rounded-xl w-full h-auto mb-4">
            <!-- Optional: Add elements for description and date if needed -->
            <!-- <div class="photo-info">
                <h2 id="photo-description" class="text-lg font-semibold"></h2>
                <p id="photo-date" class="text-sm text-gray-600"></p>
            </div> -->
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
        const nextPhotoButton = document.getElementById('next-photo-btn');

        // Function to fetch a random photo
        async function fetchRandomPhoto() {
            try {
                // Assuming a backend endpoint exists to get a random photo URL
                const response = await fetch('get_random_photo.php'); // TODO: Create this endpoint
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const photoData = await response.json(); // Assuming the endpoint returns JSON like { filename: '...' }
                console.log('Random photo data:', photoData);

                // Apply fade-out animation
                photoElement.style.opacity = 0;

                // Wait for animation to complete (adjust time as needed)
                setTimeout(() => {
                    // Update photo source
                    photoElement.src = 'uploads/' + photoData.filename;
                    // Apply fade-in animation
                    photoElement.style.opacity = 1;
                }, 300); // Match this duration with CSS transition duration
            } catch (error) {
                console.error('Error fetching random photo:', error);
                // Optionally display an error message to the user
            }
        }

        // Add event listener to the button
        nextPhotoButton.addEventListener('click', fetchRandomPhoto);

        // Fetch initial photo when the page loads
        fetchRandomPhoto();

    </script>
    <style>
        /* Add CSS for fade transition */
        #photo {
            transition: opacity 0.3s ease-in-out;
        }
    </style>
</body>
</html>