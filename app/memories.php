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

// Check if the user is part of a group
$group_id = null;
$group_name = "Skupina"; // Default value
$group_members = [];
$group_members_usernames = [];

error_log("DEBUG: Memories - Checking for group for user ID: " . $user_id);
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
            
            // Get all members of the group
            $sql_get_members = "SELECT gm.user_id, u.username
                               FROM group_members gm
                               JOIN users u ON gm.user_id = u.id
                               WHERE gm.group_id = :group_id AND gm.user_id != :user_id";
            if ($stmt_get_members = $pdo->prepare($sql_get_members)) {
                $stmt_get_members->bindParam(":group_id", $group_id, PDO::PARAM_INT);
                $stmt_get_members->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                if ($stmt_get_members->execute()) {
                    while ($member = $stmt_get_members->fetch(PDO::FETCH_ASSOC)) {
                        $group_members[] = $member['user_id'];
                        $group_members_usernames[] = htmlspecialchars($member['username']);
                    }
                }
                unset($stmt_get_members);
            }
        }
    } else {
        error_log("Error checking group status for memories: " . $stmt_check_group->errorInfo()[2]);
    }
}
unset($stmt_check_group);

// If not in a group, check if in a pair (for backward compatibility)
$pair_id = null;
$partner_id = null;
$partner_username = "Partner"; // Default value

if ($group_id === null) {
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
}

error_log("DEBUG: Memories - Group ID: " . ($group_id !== null ? $group_id : "NULL"));
error_log("DEBUG: Memories - Group Members: " . implode(", ", $group_members_usernames));
error_log("DEBUG: Memories - Pair ID: " . ($pair_id !== null ? $pair_id : "NULL"));
error_log("DEBUG: Memories - Partner ID: " . ($partner_id !== null ? $partner_id : "NULL"));

// Fetch events and their associated photos ordered by event date
$events = [];

error_log("DEBUG: Memories - Before SQL: user_id=" . $user_id . ", group_id=" . ($group_id ?? 'NULL') . ", pair_id=" . ($pair_id ?? 'NULL'));

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
    // If not in a group or pair, there should be no events linked.
    $sql = ""; // No query if not in a group or pair
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
    <title>Naše vzpomínky - <?php echo $group_id !== null ? $group_name : $_SESSION['username'] . " a " . $partner_username; ?></title>
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
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="./memories.php" class="text-gray-700 hover:text-pastel-purple">
                <?php
                if ($group_id !== null) {
                    echo htmlspecialchars($group_name);
                } else {
                    echo $_SESSION['username'] . " a " . $partner_username;
                }
                ?>
            </a></li>
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
        <h1 class="text-4xl font-bold text-pastel-purple mb-8">
            <?php
            if ($group_id !== null) {
                echo "Vzpomínky skupiny " . htmlspecialchars($group_name);
            } else {
                echo "Naše vzpomínky";
            }
            ?>
        </h1>

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
                    <div id="debugInfo" class="text-red-500 mb-4"></div>
                    <img id="modalImage" src="" alt="Event Photo" class="rounded-xl max-w-full max-h-96 object-contain mb-4"
                         onerror="document.getElementById('debugInfo').innerHTML += 'Chyba při načítání obrázku: ' + this.src + '<br>';"
                         onload="document.getElementById('debugInfo').innerHTML += 'Obrázek úspěšně načten: ' + this.src + '<br>';">
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
       // Funkce pro testování různých cest k obrázkům
       function tryImagePaths(index, pathIndex = 0, extensionIndex = 0) {
           const basePaths = [
               '../uploads/',
               '/uploads/',
               '/projectT2/uploads/',
               'uploads/'
           ];
           
           // Zkusíme různé přípony pro případ, že by byl problém v příponě souboru
           const extensions = [
               '', // Původní přípona
               '.jpg',
               '.jpeg',
               '.png'
           ];
           
           // Získáme základní název souboru bez přípony
           let filename = currentPhotos[index].filename;
           let baseFilename = filename;
           
           // Pokud extensionIndex > 0, odstraníme původní příponu a přidáme novou
           if (extensionIndex > 0) {
               const lastDotIndex = filename.lastIndexOf('.');
               if (lastDotIndex !== -1) {
                   baseFilename = filename.substring(0, lastDotIndex);
               }
               filename = baseFilename + extensions[extensionIndex];
           }
           
           const debugInfo = document.getElementById('debugInfo');
           
           // Pokud jsme vyzkoušeli všechny cesty a přípony
           if (pathIndex >= basePaths.length) {
               if (extensionIndex >= extensions.length - 1) {
                   debugInfo.innerHTML += 'Všechny cesty a přípony selhaly!<br>';
                   return;
               } else {
                   // Zkusíme další příponu
                   tryImagePaths(index, 0, extensionIndex + 1);
                   return;
               }
           }
           
           const currentPath = basePaths[pathIndex] + filename;
           debugInfo.innerHTML += 'Zkouším cestu (' + (pathIndex + 1) + '/' + basePaths.length +
                                 ') s příponou (' + (extensionIndex + 1) + '/' + extensions.length +
                                 '): ' + currentPath + '<br>';
           
           // Vytvoříme nový Image objekt pro testování cesty
           const testImg = new Image();
           testImg.onload = function() {
               debugInfo.innerHTML += 'Cesta funguje: ' + currentPath + '<br>';
               modalImage.src = currentPath;
           };
           testImg.onerror = function() {
               debugInfo.innerHTML += 'Cesta selhala: ' + currentPath + '<br>';
               // Zkusíme další cestu
               tryImagePaths(index, pathIndex + 1, extensionIndex);
           };
           testImg.src = currentPath;
       }

       // Funkce pro kontrolu dostupnosti obrázku pomocí XMLHttpRequest (pro odhalení CORS problémů)
       function checkImageAvailability(url) {
           const debugInfo = document.getElementById('debugInfo');
           debugInfo.innerHTML += 'Kontroluji dostupnost obrázku pomocí XHR: ' + url + '<br>';
           
           const xhr = new XMLHttpRequest();
           xhr.open('HEAD', url, true);
           xhr.onload = function() {
               if (xhr.status >= 200 && xhr.status < 300) {
                   debugInfo.innerHTML += 'XHR: Obrázek je dostupný: ' + url + ' (Status: ' + xhr.status + ')<br>';
               } else {
                   debugInfo.innerHTML += 'XHR: Obrázek není dostupný: ' + url + ' (Status: ' + xhr.status + ')<br>';
               }
           };
           xhr.onerror = function() {
               debugInfo.innerHTML += 'XHR: Chyba při kontrole dostupnosti obrázku: ' + url + '<br>';
           };
           xhr.send();
       }

       function displayPhoto(index) {
           if (currentPhotos.length > 0 && index >= 0 && index < currentPhotos.length) {
               const debugInfo = document.getElementById('debugInfo');
               debugInfo.innerHTML = 'Zobrazuji fotku ' + (index + 1) + ' z ' + currentPhotos.length + '<br>';
               debugInfo.innerHTML += 'Filename: ' + currentPhotos[index].filename + '<br>';
               
               // Zkontrolujeme dostupnost obrázku pomocí XHR
               checkImageAvailability('../uploads/' + currentPhotos[index].filename);
               
               // Zkusíme různé cesty k obrázkům
               tryImagePaths(index);
               
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
           const debugInfo = document.getElementById('debugInfo');
           debugInfo.innerHTML = 'Otevírám modální okno pro událost ID: ' + eventId + '<br>';
           
           modalImage.src = ''; // Clear previous image
           modalDescription.textContent = ''; // Clear previous description
           photoModal.classList.remove('hidden'); // Show modal while loading

           // Fetch photos for the event via AJAX
           debugInfo.innerHTML += 'Načítám fotky pomocí AJAX...<br>';
           console.log('Fetching photos for event ID:', eventId);
           
           fetch(`../app/get_event_photos.php?event_id=${eventId}`)
               .then(response => {
                   debugInfo.innerHTML += 'AJAX odpověď status: ' + response.status + '<br>';
                   console.log('AJAX response:', response);
                   
                   if (!response.ok) {
                       throw new Error('Network response was not ok: ' + response.status);
                   }
                   return response.json();
               })
               .then(photos => {
                   debugInfo.innerHTML += 'Přijato fotek: ' + (photos ? photos.length : 0) + '<br>';
                   console.log('Received photos data:', photos);
                   
                   // Podrobné logování dat z AJAX požadavku
                   if (photos && photos.length > 0) {
                       debugInfo.innerHTML += '<hr>Detaily první fotky:<br>';
                       for (const key in photos[0]) {
                           debugInfo.innerHTML += key + ': ' + photos[0][key] + '<br>';
                       }
                       debugInfo.innerHTML += '<hr>';
                   }
                   
                   currentPhotos = photos;
                   if (currentPhotos && currentPhotos.length > 0) {
                       debugInfo.innerHTML += 'Zobrazuji první fotku...<br>';
                       displayPhoto(0); // Display the first photo
                   } else {
                       debugInfo.innerHTML += 'Žádné fotky nebyly nalezeny<br>';
                       modalDescription.textContent = 'K této události nejsou k dispozici žádné fotky.';
                       modalImage.src = ''; // Ensure no image is shown
                       prevPhotoBtn.disabled = true;
                       nextPhotoBtn.disabled = true;
                   }
               })
               .catch(error => {
                   debugInfo.innerHTML += 'Chyba: ' + error.message + '<br>';
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