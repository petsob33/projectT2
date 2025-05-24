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

// Fetch pending group invitations for the current user
$pending_invitations = [];
$sql_get_invitations = "SELECT gi.id, gi.group_id, g.name AS group_name, u.username AS inviter_username, gi.created_at
                        FROM group_invitations gi
                        JOIN groups g ON gi.group_id = g.id
                        JOIN users u ON gi.inviter_id = u.id
                        WHERE gi.invitee_id = :user_id AND gi.status = 'pending'
                        ORDER BY gi.created_at DESC";

if ($stmt_get_invitations = $pdo->prepare($sql_get_invitations)) {
    $stmt_get_invitations->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_get_invitations->execute()) {
        $pending_invitations = $stmt_get_invitations->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching group invitations: " . $stmt_get_invitations->errorInfo()[2]);
    }
}
unset($stmt_get_invitations);

// Fetch groups the user is a member of
$user_groups = [];
$sql_get_groups = "SELECT g.id, g.name, g.established_at, COUNT(gm.id) AS member_count
                   FROM groups g
                   JOIN group_members gm ON g.id = gm.group_id
                   WHERE g.id IN (SELECT group_id FROM group_members WHERE user_id = :user_id)
                   GROUP BY g.id
                   ORDER BY g.established_at DESC";

if ($stmt_get_groups = $pdo->prepare($sql_get_groups)) {
    $stmt_get_groups->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_get_groups->execute()) {
        $user_groups = $stmt_get_groups->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Error fetching user groups: " . $stmt_get_groups->errorInfo()[2]);
    }
}
unset($stmt_get_groups);

// Close connection
unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skupiny a pozvánky</title>
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
        <div class="text-3xl font-bold text-pastel-purple">Skupiny a pozvánky</div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="./memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="./pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="./group_invitations.php" class="text-gray-700 hover:text-pastel-purple">Skupiny a pozvánky</a></li>
            <li class="mb-2"><a href="../public/relationship_duration.php" class="text-gray-700 hover:text-pastel-purple">Délka vztahu</a></li>
            <li class="mb-2"><a href="../admin/dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="../public/logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow p-4">
        <div class="max-w-4xl mx-auto">
            <!-- Create Group Section -->
            <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Vytvořit novou skupinu</h2>
                <form id="create-group-form" class="space-y-4">
                    <div>
                        <label for="group-name" class="block text-gray-700 mb-2">Název skupiny</label>
                        <input type="text" id="group-name" name="group_name" class="w-full p-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label for="start-date" class="block text-gray-700 mb-2">Datum začátku (volitelné)</label>
                        <input type="date" id="start-date" name="start_date" class="w-full p-2 border border-gray-300 rounded-lg">
                    </div>
                    <button type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300">
                        Vytvořit skupinu
                    </button>
                </form>
                <div id="create-group-message" class="mt-4 hidden"></div>
            </div>

            <!-- My Groups Section -->
            <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Moje skupiny</h2>
                <?php if (empty($user_groups)): ?>
                    <p class="text-gray-600">Nejste členem žádné skupiny.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($user_groups as $group): ?>
                            <div class="border border-gray-200 p-4 rounded-lg">
                                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($group['name']); ?></h3>
                                <p class="text-gray-600">Počet členů: <?php echo $group['member_count']; ?></p>
                                <div class="mt-2 flex space-x-2">
                                    <button class="invite-button bg-pastel-pink text-pastel-purple font-bold py-1 px-3 rounded-lg shadow hover:opacity-90 transition duration-300" 
                                            data-group-id="<?php echo $group['id']; ?>" 
                                            data-group-name="<?php echo htmlspecialchars($group['name']); ?>">
                                        Pozvat uživatele
                                    </button>
                                    <a href="./memories.php" class="bg-gray-200 text-gray-700 font-bold py-1 px-3 rounded-lg shadow hover:opacity-90 transition duration-300">
                                        Zobrazit vzpomínky
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Invitations Section -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Pozvánky do skupin</h2>
                <?php if (empty($pending_invitations)): ?>
                    <p class="text-gray-600">Nemáte žádné nevyřízené pozvánky do skupin.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_invitations as $invitation): ?>
                            <div class="border border-gray-200 p-4 rounded-lg">
                                <p class="text-gray-800">
                                    <span class="font-bold"><?php echo htmlspecialchars($invitation['inviter_username']); ?></span> 
                                    vás pozval do skupiny 
                                    <span class="font-bold"><?php echo htmlspecialchars($invitation['group_name']); ?></span>
                                </p>
                                <p class="text-gray-600 text-sm">
                                    <?php echo date('d.m.Y H:i', strtotime($invitation['created_at'])); ?>
                                </p>
                                <div class="mt-2 flex space-x-2">
                                    <button class="handle-invitation-button bg-pastel-pink text-pastel-purple font-bold py-1 px-3 rounded-lg shadow hover:opacity-90 transition duration-300" 
                                            data-invitation-id="<?php echo $invitation['id']; ?>" 
                                            data-action="accept">
                                        Přijmout
                                    </button>
                                    <button class="handle-invitation-button bg-gray-200 text-gray-700 font-bold py-1 px-3 rounded-lg shadow hover:opacity-90 transition duration-300" 
                                            data-invitation-id="<?php echo $invitation['id']; ?>" 
                                            data-action="reject">
                                        Odmítnout
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invite User Modal -->
        <div id="invite-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="bg-white p-6 rounded-xl shadow-lg max-w-md w-full">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Pozvat uživatele do skupiny</h2>
                <p id="invite-group-name" class="text-gray-600 mb-4"></p>
                <form id="invite-form" class="space-y-4">
                    <input type="hidden" id="invite-group-id" name="group_id">
                    <div>
                        <label for="recipient-username" class="block text-gray-700 mb-2">Uživatelské jméno</label>
                        <input type="text" id="recipient-username" name="recipient_username" class="w-full p-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" id="close-invite-modal" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg shadow hover:opacity-90 transition duration-300">
                            Zrušit
                        </button>
                        <button type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-lg shadow hover:opacity-90 transition duration-300">
                            Odeslat pozvánku
                        </button>
                    </div>
                </form>
                <div id="invite-message" class="mt-4 hidden"></div>
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

        // Create Group Form
        const createGroupForm = document.getElementById('create-group-form');
        const createGroupMessage = document.getElementById('create-group-message');

        createGroupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(createGroupForm);
            
            try {
                const response = await fetch('../app/create_group.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                createGroupMessage.classList.remove('hidden', 'text-red-500', 'text-green-500');
                
                if (response.ok) {
                    createGroupMessage.classList.add('text-green-500');
                    createGroupMessage.textContent = data.message;
                    createGroupForm.reset();
                    // Reload the page after a short delay to show the new group
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    createGroupMessage.classList.add('text-red-500');
                    createGroupMessage.textContent = data.error || 'Došlo k chybě při vytváření skupiny.';
                }
            } catch (error) {
                console.error('Error:', error);
                createGroupMessage.classList.remove('hidden');
                createGroupMessage.classList.add('text-red-500');
                createGroupMessage.textContent = 'Došlo k chybě při komunikaci se serverem.';
            }
        });

        // Invite User Modal
        const inviteButtons = document.querySelectorAll('.invite-button');
        const inviteModal = document.getElementById('invite-modal');
        const closeInviteModalButton = document.getElementById('close-invite-modal');
        const inviteForm = document.getElementById('invite-form');
        const inviteGroupId = document.getElementById('invite-group-id');
        const inviteGroupName = document.getElementById('invite-group-name');
        const inviteMessage = document.getElementById('invite-message');

        inviteButtons.forEach(button => {
            button.addEventListener('click', () => {
                const groupId = button.getAttribute('data-group-id');
                const groupName = button.getAttribute('data-group-name');
                
                inviteGroupId.value = groupId;
                inviteGroupName.textContent = `Skupina: ${groupName}`;
                
                inviteModal.classList.remove('hidden');
                inviteMessage.classList.add('hidden');
            });
        });

        closeInviteModalButton.addEventListener('click', () => {
            inviteModal.classList.add('hidden');
            inviteForm.reset();
        });

        // Close modal when clicking outside
        inviteModal.addEventListener('click', (e) => {
            if (e.target === inviteModal) {
                inviteModal.classList.add('hidden');
                inviteForm.reset();
            }
        });

        inviteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(inviteForm);
            
            try {
                const response = await fetch('../app/send_group_invitation.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                inviteMessage.classList.remove('hidden', 'text-red-500', 'text-green-500');
                
                if (response.ok) {
                    inviteMessage.classList.add('text-green-500');
                    inviteMessage.textContent = data.message;
                    inviteForm.reset();
                    // Close the modal after a short delay
                    setTimeout(() => {
                        inviteModal.classList.add('hidden');
                    }, 1500);
                } else {
                    inviteMessage.classList.add('text-red-500');
                    inviteMessage.textContent = data.error || 'Došlo k chybě při odesílání pozvánky.';
                }
            } catch (error) {
                console.error('Error:', error);
                inviteMessage.classList.remove('hidden');
                inviteMessage.classList.add('text-red-500');
                inviteMessage.textContent = 'Došlo k chybě při komunikaci se serverem.';
            }
        });

        // Handle Invitation Buttons
        const handleInvitationButtons = document.querySelectorAll('.handle-invitation-button');

        handleInvitationButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const invitationId = button.getAttribute('data-invitation-id');
                const action = button.getAttribute('data-action');
                
                const formData = new FormData();
                formData.append('invitation_id', invitationId);
                formData.append('action', action);
                
                try {
                    const response = await fetch('../app/handle_group_invitation.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        // Reload the page to reflect the changes
                        window.location.reload();
                    } else {
                        alert(data.error || 'Došlo k chybě při zpracování pozvánky.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Došlo k chybě při komunikaci se serverem.');
                }
            });
        });
    </script>
</body>
</html>