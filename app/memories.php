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

// Fetch events and their associated photos ordered by event date
$events = [];

error_log("DEBUG: Memories - Before SQL: user_id=" . $user_id . ", pair_id=" . ($pair_id ?? 'NULL') . ", partner_id=" . ($partner_id ?? 'NULL'));

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

// If not paired, fetch events and photos only for the current user (if applicable, though events are designed for pairs)
// Note: The current schema links events to pairs. If a user is not paired, they won't have events linked this way.
// We might need to adjust the logic if single users can also create events. For now, assuming events are for pairs.
if ($pair_id === null) {
    // If not paired, there should be no events linked to a pair_id.
    // We could potentially fetch photos not linked to an event for the user,
    // but the request is about grouping by event.
    // For now, if not paired, $events will be empty, which is handled in the HTML.
    $sql = ""; // No query if not paired based on current schema
    $params = [];
}


error_log("DEBUG: Memories - SQL Query: " . $sql);
error_log("DEBUG: Memories - SQL Params: " . json_encode($params));

if (!empty($sql) && $stmt = $pdo->prepare($sql)) {
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
        <h1 class="text-4xl font-bold text-pastel-purple mb-8">Naše vzpomínky</h1>

        <div class="memories-list w-full max-w-sm">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-item bg-white p-4 rounded-xl shadow-lg mb-6 w-full max-w-sm mx-auto">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $event['name']; ?></h2>
                        <p class="text-sm text-gray-600 mb-4"><?php echo $event['date']; ?></p>

                        <?php if (!empty($event['photos'])): ?>
                            <div class="event-thumbnail mb-4 cursor-pointer" data-event-id="<?php echo $event['id']; ?>">
                                <img src="../uploads/<?php echo $event['photos'][0]['filename']; ?>" alt="Thumbnail for <?php echo $event['name']; ?>" class="rounded-xl w-full h-auto">
                            </div>
                            <p class="text-gray-700"><?php echo $event['photos'][0]['description']; ?></p>
                            <p class="text-gray-700"><?php echo $event['photos'][0]['description']; ?></p>
                        <?php else: ?>
                            <p class="text-gray-600">K této události nejsou přiřazeny žádné fotky.</p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-black w-0.5 h-16 mx-auto my-6"></div> <!-- Vertical line below event -->
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-600">Zatím zde nejsou žádné události s fotkami.</p>
            <?php endif; ?>
        </div>

        <!-- Modal for displaying all photos in an event -->
        <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="modal-content bg-white p-4 rounded-xl shadow-lg max-w-3xl max-h-full overflow-y-auto relative">
                <span class="close-button absolute top-2 right-2 text-gray-600 text-2xl font-bold cursor-pointer">&times;</span>
                <div id="modalContentInner" class="flex flex-col items-center">
                    <img id="modalImage" src="" alt="Event Photo" class="rounded-xl max-w-full max-h-96 object-contain mb-4">
                    <p id="modalDescription" class="text-gray-700 mb-4"></p>
                    <div class="flex justify-between w-full max-w-xs">
                        <button id="prevPhoto" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer">< Předchozí</button>
                        <button id="nextPhoto" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer">Další ></button>
                    </div>
                </div>
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
       console.log('Memories event data:', eventsData);

       // Modal functionality
       const photoModal = document.getElementById('photoModal');
       const modalImage = document.getElementById('modalImage');
       const modalDescription = document.getElementById('modalDescription');
       const closeButton = document.querySelector('.close-button');
       // Removed viewPhotosButtons as the button is removed
       const eventThumbnails = document.querySelectorAll('.event-thumbnail');
       const prevPhotoBtn = document.getElementById('prevPhoto');
       const nextPhotoBtn = document.getElementById('nextPhoto');

       let currentPhotos = [];
       let currentPhotoIndex = 0;

       // Function to display a specific photo in the modal
       function displayPhoto(index) {
           if (currentPhotos.length > 0 && index >= 0 && index < currentPhotos.length) {
               modalImage.src = '../uploads/' + currentPhotos[index].filename;
               modalImage.alt = currentPhotos[index].description;
               modalDescription.textContent = currentPhotos[index].description || ''; // Display description or empty string
               currentPhotoIndex = index;

               // Enable/disable navigation buttons
               prevPhotoBtn.disabled = currentPhotoIndex === 0;
               nextPhotoBtn.disabled = currentPhotoIndex === currentPhotos.length - 1;
           }
       }

       // Function to open the modal and fetch/display photos
       function openModal(eventId) {
           modalImage.src = ''; // Clear previous image
           modalDescription.textContent = ''; // Clear previous description
           photoModal.classList.remove('hidden'); // Show modal while loading

           // Fetch photos for the event via AJAX
           fetch(`../app/get_event_photos.php?event_id=${eventId}`)
               .then(response => {
                   if (!response.ok) {
                       throw new Error('Network response was not ok');
                   }
                   return response.json();
               })
               .then(photos => {
                   currentPhotos = photos;
                   if (currentPhotos.length > 0) {
                       displayPhoto(0); // Display the first photo
                   } else {
                       modalDescription.textContent = 'K této události nejsou k dispozici žádné fotky.';
                       modalImage.src = ''; // Ensure no image is shown
                       prevPhotoBtn.disabled = true;
                       nextPhotoBtn.disabled = true;
                   }
               })
               .catch(error => {
                   console.error('Error fetching photos:', error);
                   modalDescription.textContent = 'Chyba při načítání fotek.';
                   modalImage.src = ''; // Ensure no image is shown
                   prevPhotoBtn.disabled = true;
                   nextPhotoBtn.disabled = true;
               });
       }

       // Event listeners for buttons and thumbnails
       // Removed event listener for viewPhotosButtons as the button is removed
       eventThumbnails.forEach(thumbnail => {
           thumbnail.addEventListener('click', () => {
               const eventId = thumbnail.getAttribute('data-event-id');
               openModal(eventId);
           });
       });

       // Event listeners for navigation buttons
       prevPhotoBtn.addEventListener('click', () => {
           displayPhoto(currentPhotoIndex - 1);
       });

       nextPhotoBtn.addEventListener('click', () => {
           displayPhoto(currentPhotoIndex + 1);
       });


      // Event listener for closing the modal
      closeButton.addEventListener('click', () => {
          photoModal.classList.add('hidden');
      });

      // Close modal when clicking outside the modal content
      window.addEventListener('click', (event) => {
          if (event.target === photoModal) {
              photoModal.classList.add('hidden');
          }
      });

   </script>
</body>
</html>