<?php
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: ../login.php");
    exit;
}

// Fetch photos from the database
$photos = [];
$sql = "SELECT id, filename, description, date FROM photos ORDER BY date DESC"; // Reverted to 'date' based on database error
if ($stmt = $pdo->prepare($sql)) {
    if ($stmt->execute()) {
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Handle error - log or display a message
        echo "Oops! Something went wrong. Please try again later.";
    }
}
unset($stmt);

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
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="../index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="../pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="../logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center p-4">
        <div class="admin-dashboard-content bg-white p-6 rounded-xl shadow-lg w-full max-w-4xl">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Admin Dashboard</h1>
            <p class="text-gray-700 mb-6">Vítejte, <?php echo htmlspecialchars($_SESSION["username"]); ?>.</p>

            <div class="mb-6">
                <a href="upload.php" class="inline-block bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300">Nahrát novou fotku</a>
            </div>

            <h2 class="text-3xl font-bold text-pastel-purple mb-4">Spravovat fotky</h2>
            <div class="photo-list overflow-x-auto">
                <?php if (empty($photos)): ?>
                    <p class="text-gray-600">Zatím zde nejsou žádné fotky.</p>
                <?php else: ?>
                    <table class="min-w-full bg-white rounded-xl overflow-hidden">
                        <thead>
                            <tr class="bg-pastel-pink text-pastel-purple uppercase text-base leading-normal">
                                <th class="py-3 px-6 text-left">Náhled</th>
                                <th class="py-3 px-6 text-left">Popisek</th>
                                <th class="py-3 px-6 text-left">Datum</th>
                                <th class="py-3 px-6 text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-base font-light">
                            <?php foreach ($photos as $photo): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="py-3 px-6 text-left whitespace-nowrap">
                                        <img src="../uploads/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo htmlspecialchars($photo['description']); ?>" class="w-16 h-16 object-cover rounded">
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($photo['description']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($photo['date']); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <a href="delete.php?id=<?php echo $photo['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Opravdu chcete smazat tuto fotku?');">Smazat</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
       // Log photo data to console for debugging
       const photosData = <?php echo json_encode($photos); ?>;
       console.log('Admin Dashboard photo data:', photosData);
   </script>
</body>
</html>