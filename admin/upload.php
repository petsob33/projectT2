<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Define a constant to prevent direct access to include files
define('INCLUDE_CHECK', true);

// Test, zda má PHP oprávnění k zápisu do adresáře uploads
$test_file = "../uploads/test.txt";
$test_content = "Test zápisu do adresáře uploads: " . date('Y-m-d H:i:s');
$write_result = @file_put_contents($test_file, $test_content);
if ($write_result === false) {
    error_log("KRITICKÁ CHYBA: Nelze zapisovat do adresáře uploads. Chyba: " . error_get_last()['message']);
} else {
    error_log("Test zápisu do adresáře uploads byl úspěšný. Zapsáno " . $write_result . " bajtů.");
    // Smažeme testovací soubor
    @unlink($test_file);
}

// Include config file
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Check if the user is logged in, if not then redirect to login page
if (!is_logged_in()) {
    header("location: ../public/login.php");
    exit;
}

// Define variables and initialize with empty values
$event_name = $description = $date = "";
$event_name_err = $description_err = $date_err = $photo_err = "";

// Get user information
$user_id = $_SESSION['id'];
$group_id = null;
$group_name = null;
$pair_id = null;
$partner_username = "Partner"; // Default value

// Check if the user is part of a group
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

// Function to compress image while preserving orientation and aspect ratio
function compressImage($source, $destination, $quality, $target_size = 500000) {
    // Přidáme logování
    error_log("compressImage: Začínám kompresi obrázku");
    error_log("compressImage: Zdrojový soubor: " . $source);
    error_log("compressImage: Cílový soubor: " . $destination);
    
    // Get image information including EXIF data
    $info = getimagesize($source);
    if (!$info) {
        error_log("compressImage: Nepodařilo se získat informace o obrázku");
        return false;
    }
    error_log("compressImage: MIME typ obrázku: " . $info['mime']);
    
    $exif = @exif_read_data($source);

    if ($info['mime'] == 'image/jpeg' || $info['mime'] == 'image/jpg') {
        error_log("compressImage: Zpracovávám JPEG/JPG obrázek");
        $image = @imagecreatefromjpeg($source);
        if (!$image) {
            error_log("compressImage: Nepodařilo se načíst JPEG/JPG obrázek. Chyba: " . error_get_last()['message']);
            return false;
        }
    } elseif ($info['mime'] == 'image/png') {
        error_log("compressImage: Zpracovávám PNG obrázek");
        $image = @imagecreatefrompng($source);
        if (!$image) {
            error_log("compressImage: Nepodařilo se načíst PNG obrázek. Chyba: " . error_get_last()['message']);
            return false;
        }
    } elseif ($info['mime'] == 'image/gif') {
        error_log("compressImage: Zpracovávám GIF obrázek");
        $image = @imagecreatefromgif($source);
        if (!$image) {
            error_log("compressImage: Nepodařilo se načíst GIF obrázek. Chyba: " . error_get_last()['message']);
            return false;
        }
    } else {
        error_log("compressImage: Nepodporovaný typ obrázku: " . $info['mime']);
        return false;
    }

    // Initial quality
    $curr_quality = $quality;
    $max_attempts = 5;
    $attempts = 0;

    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);

    // Fix orientation based on EXIF data
    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                // Swap width and height
                $tmp = $width;
                $width = $height;
                $height = $tmp;
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                // Swap width and height
                $tmp = $width;
                $width = $height;
                $height = $tmp;
                break;
        }
    }

    // Zkusíme nejprve uložit jednoduchý testovací soubor, abychom ověřili oprávnění k zápisu
    $test_content = "Test zápisu do adresáře: " . date('Y-m-d H:i:s');
    $test_file = dirname($destination) . "/test_" . uniqid() . ".txt";
    $write_result = @file_put_contents($test_file, $test_content);
    
    if ($write_result === false) {
        error_log("compressImage: KRITICKÁ CHYBA: Nelze zapisovat do adresáře " . dirname($destination) . ". Chyba: " . error_get_last()['message']);
        return false;
    } else {
        error_log("compressImage: Test zápisu do adresáře byl úspěšný. Zapsáno " . $write_result . " bajtů do " . $test_file);
        // Smažeme testovací soubor
        @unlink($test_file);
    }
    
    // Try just quality reduction first (without resizing)
    $result = imagejpeg($image, $destination, $curr_quality);
    if (!$result) {
        error_log("compressImage: Nepodařilo se uložit obrázek pomocí imagejpeg. Chyba: " . error_get_last()['message']);
        return false;
    }
    error_log("compressImage: Obrázek byl úspěšně uložen pomocí imagejpeg do " . $destination);

    // Check if target size is reached
    if (!file_exists($destination)) {
        error_log("compressImage: Cílový soubor neexistuje po uložení");
        return false;
    }
    
    $current_size = filesize($destination);
    error_log("compressImage: Velikost souboru po kompresi: " . $current_size . " bajtů");

    // If file is still too large, gradually reduce quality and resize if necessary
    while ($current_size > $target_size && $attempts < $max_attempts) {
        $attempts++;

        // First try just reducing quality more
        if ($attempts <= 2) {
            $curr_quality = max($curr_quality - 10, 50); // Reduce quality more aggressively
            imagejpeg($image, $destination, $curr_quality);
        }
        // If that doesn't work, then resize while preserving aspect ratio
        else {
            // Calculate new dimensions (reduce by 15% each remaining attempt)
            $scale = 1 - (($attempts - 2) * 0.15);
            $new_width = max(round($width * $scale), 800); // Don't go below 800px width
            $new_height = round($height * ($new_width / $width));

            // Create new image with correct aspect ratio
            $new_image = imagecreatetruecolor($new_width, $new_height);

            // Preserve transparency for PNG images
            if ($info['mime'] == 'image/png') {
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
                $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
                imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
            }

            // Copy and resize the image
            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            // Set quality to a good value
            $curr_quality = 40;

            // Save compressed image
            imagejpeg($new_image, $destination, $curr_quality);
            imagedestroy($new_image);
        }

        // Check new size
        $current_size = filesize($destination);
    }

    imagedestroy($image);
    error_log("compressImage: Komprese dokončena úspěšně");
    return true;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate event name
    if (empty(trim($_POST["event_name"]))) {
        $event_name_err = "Prosím, zadejte název události.";
    } else {
        $event_name = trim($_POST["event_name"]);
    }

    // Validate date
    if (empty(trim($_POST["date"]))) {
        $date_err = "Prosím, vyberte datum pro událost.";
    } else {
        $date = trim($_POST["date"]);
        // Optional: Add more date validation if needed (e.g., valid date format)
    }

    // Validate description (optional)
    $description = trim($_POST["description"]);


    // Check if files were uploaded without errors
    if (isset($_FILES["photos"]) && !empty($_FILES["photos"]["name"][0])) {
        $allowed_types = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $upload_dir = "../uploads";
        $total_files = count($_FILES["photos"]["name"]);
        $uploaded_count = 0;
        $errors = [];

        // Check if there were no event name or date errors
        if (empty($event_name_err) && empty($date_err)) {

            // Determine user, group, or pair
            $user_id = $_SESSION['id'];
            $group_id = null;
            $pair_id = null;

            // Check if the user is part of a group
            $sql_check_group = "SELECT g.id FROM groups g
                                JOIN group_members gm ON g.id = gm.group_id
                                WHERE gm.user_id = :user_id
                                LIMIT 1";
            if ($stmt_check_group = $pdo->prepare($sql_check_group)) {
                $stmt_check_group->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                if ($stmt_check_group->execute()) {
                    $group = $stmt_check_group->fetch(PDO::FETCH_ASSOC);
                    if ($group) {
                        $group_id = $group['id'];
                    }
                } else {
                    error_log("Error checking group status for upload: " . $stmt_check_group->errorInfo()[2]);
                }
            }
            unset($stmt_check_group);

            // If not in a group, check if in a pair (for backward compatibility)
            if ($group_id === null) {
                $sql_check_pair = "SELECT id FROM pairs WHERE user1_id = :user_id OR user2_id = :user_id LIMIT 1";
                if ($stmt_check_pair = $pdo->prepare($sql_check_pair)) {
                    $stmt_check_pair->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                    if ($stmt_check_pair->execute()) {
                        $pair = $stmt_check_pair->fetch(PDO::FETCH_ASSOC);
                        if ($pair) {
                            $pair_id = $pair['id'];
                        }
                    } else {
                        error_log("Error checking pair status for upload: " . $stmt_check_pair->errorInfo()[2]);
                    }
                }
                unset($stmt_check_pair);
            }

            // Insert event into database
            $sql_insert_event = "INSERT INTO events (name, date, pair_id, group_id) VALUES (:name, :date, :pair_id, :group_id)";
            if ($stmt_insert_event = $pdo->prepare($sql_insert_event)) {
                $stmt_insert_event->bindParam(":name", $event_name, PDO::PARAM_STR);
                $stmt_insert_event->bindParam(":date", $date, PDO::PARAM_STR);
                $stmt_insert_event->bindParam(":pair_id", $pair_id, PDO::PARAM_INT);
                $stmt_insert_event->bindParam(":group_id", $group_id, PDO::PARAM_INT);

                if ($stmt_insert_event->execute()) {
                    $event_id = $pdo->lastInsertId();

                    // Process each uploaded file
                    for ($i = 0; $i < $total_files; $i++) {
                        $file_name = $_FILES["photos"]["name"][$i];
                        $file_type = $_FILES["photos"]["type"][$i];
                        $file_size = $_FILES["photos"]["size"][$i];
                        $temp_file = $_FILES["photos"]["tmp_name"][$i];
                        $file_error = $_FILES["photos"]["error"][$i];

                        // Check for upload errors
                        if ($file_error !== UPLOAD_ERR_OK) {
                            $errors[] = "Chyba při nahrávání souboru " . htmlspecialchars($file_name) . ": Kód chyby " . $file_error;
                            continue;
                        }

                        // Verify file extension
                        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        if (!array_key_exists(strtolower($ext), $allowed_types)) {
                            $errors[] = "Chyba: Soubor " . htmlspecialchars($file_name) . " má neplatný formát. Povolené formáty: JPG, JPEG, GIF, PNG.";
                            continue;
                        }

                        // Verify file size - 10MB maximum (initial upload size limit)
                        $maxsize = 10 * 1024 * 1024;
                        if ($file_size > $maxsize) {
                            $errors[] = "Chyba: Velikost souboru " . htmlspecialchars($file_name) . " je větší než povolený limit (10MB).";
                            continue;
                        }

                        // Verify MIME type of the file
                        if (!in_array($file_type, $allowed_types)) {
                             $errors[] = "Chyba: Při nahrávání souboru " . htmlspecialchars($file_name) . " došlo k problému s typem souboru. Zkuste to prosím znovu.";
                             continue;
                        }


                        // Generate a unique filename
                        $new_filename = uniqid() . ".jpg"; // Always save as JPG after compression
                        $upload_path = $upload_dir . $new_filename;

                        // Kontrola, zda adresář uploads existuje a má správná oprávnění
                        if (!is_dir($upload_dir)) {
                            error_log("Upload: Adresář uploads neexistuje: " . $upload_dir);
                            mkdir($upload_dir, 0755, true);
                            error_log("Upload: Vytvořen adresář uploads: " . $upload_dir);
                        }
                        
                        if (!is_writable($upload_dir)) {
                            error_log("Upload: Adresář uploads není zapisovatelný: " . $upload_dir);
                            $errors[] = "Chyba: Adresář pro nahrávání fotek není zapisovatelný. Kontaktujte správce systému.";
                            continue;
                        }
                        
                        error_log("Upload: Adresář uploads existuje a je zapisovatelný: " . $upload_dir);
                        error_log("Upload: Dočasný soubor: " . $temp_file);
                        error_log("Upload: Cílová cesta: " . $upload_path);
                        error_log("Upload: Typ souboru: " . $file_type);
                        error_log("Upload: Velikost souboru: " . $file_size . " bajtů");
                        
                        // Zkusíme nejprve zkopírovat soubor bez komprese, abychom zjistili, zda je problém v kopírování nebo v kompresi
                        $copy_path = $upload_dir . "original_" . $new_filename;
                        if (copy($temp_file, $copy_path)) {
                            error_log("Upload: Soubor byl úspěšně zkopírován bez komprese: " . $copy_path);
                        } else {
                            error_log("Upload: Nepodařilo se zkopírovat soubor bez komprese: " . $copy_path . ". Chyba: " . error_get_last()['message']);
                        }
                        
                        // Compress the image before saving
                        if (compressImage($temp_file, $upload_path, 85, 500000)) { // Target size: 500KB (500,000 bytes)
                            // File uploaded and compressed successfully, insert into database
                            error_log("Upload: Obrázek byl úspěšně komprimován a uložen: " . $upload_path);
                            $sql_insert_photo = "INSERT INTO photos (filename, description, user_id, event_id, pair_id, group_id)
                                                VALUES (:filename, :description, :user_id, :event_id, :pair_id, :group_id)";
                            if ($stmt_insert_photo = $pdo->prepare($sql_insert_photo)) {
                                $stmt_insert_photo->bindParam(":filename", $new_filename, PDO::PARAM_STR);
                                $stmt_insert_photo->bindParam(":description", $description, PDO::PARAM_STR);
                                $stmt_insert_photo->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                                $stmt_insert_photo->bindParam(":event_id", $event_id, PDO::PARAM_INT);
                                $stmt_insert_photo->bindParam(":pair_id", $pair_id, PDO::PARAM_INT);
                                $stmt_insert_photo->bindParam(":group_id", $group_id, PDO::PARAM_INT);

                                if ($stmt_insert_photo->execute()) {
                                    $uploaded_count++;
                                } else {
                                    $errors[] = "Chyba: Při vkládání informací o fotce " . htmlspecialchars($file_name) . " do databáze došlo k problému. Zkuste to prosím znovu později.";
                                    // Optionally delete the uploaded file if database insertion fails
                                    unlink($upload_path);
                                }
                                unset($stmt_insert_photo);
                            } else {
                                $errors[] = "Chyba: Při přípravě dotazu na vložení fotky " . htmlspecialchars($file_name) . " došlo k problému.";
                                unlink($upload_path);
                            }
                        } else {
                            $errors[] = "Chyba: Při kompresi souboru " . htmlspecialchars($file_name) . " došlo k problému. Zkuste to prosím znovu.";
                        }
                    }

                    // Check if any files were uploaded successfully
                    if ($uploaded_count > 0 && empty($errors)) {
                        // Redirect to dashboard
                        header("location: ./dashboard.php");
                        exit();
                    } elseif (!empty($errors)) {
                        // Display accumulated errors
                        $photo_err = implode("<br>", $errors);
                        // Optionally delete the event if no photos were successfully uploaded
                         if ($uploaded_count === 0) {
                             $sql_delete_event = "DELETE FROM events WHERE id = :event_id";
                             if ($stmt_delete_event = $pdo->prepare($sql_delete_event)) {
                                 $stmt_delete_event->bindParam(":event_id", $event_id, PDO::PARAM_INT);
                                 $stmt_delete_event->execute();
                             }
                             unset($stmt_delete_event);
                         }
                    } else {
                         $photo_err = "Nebyly nahrány žádné platné soubory fotek.";
                    }

                } else {
                    echo "Chyba: Při vkládání události do databáze došlo k problému. Zkuste to prosím znovu později.";
                }
                unset($stmt_insert_event);
            } else {
                 echo "Chyba: Při přípravě dotazu na vložení události došlo k problému.";
            }

        }

    } else {
        // Handle cases where no files were uploaded or there was an upload error
        if (isset($_FILES["photos"]) && $_FILES["photos"]["error"][0] != UPLOAD_ERR_NO_FILE) {
             $photo_err = "Chyba při nahrávání souborů: Kód chyby " . $_FILES["photos"]["error"][0];
        } else {
             $photo_err = "Prosím, vyberte alespoň jednu fotku k nahrání.";
        }
    }

    // Close connection
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nahrát fotku - Naše fotky</title>
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
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple">Naše vzpomínky</a></li>
            <li class="mb-2"><a href="../app/pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
            <li class="mb-2"><a href="../app/group_invitations.php" class="text-gray-700 hover:text-pastel-purple">Skupiny a pozvánky</a></li>
            <li class="mb-2"><a href="./dashboard.php" class="text-gray-700 hover:text-pastel-purple">Admin</a></li>
            <?php if (is_logged_in()): ?>
                <li class="mb-2"><a href="../public/logout.php" class="text-gray-700 hover:text-pastel-purple">Odhlásit se</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Overlay for closing sidebar -->
    <div class="overlay fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

    <main class="flex-grow flex flex-col items-center p-4">
        <div class="admin-upload-content bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
            <h1 class="text-4xl font-bold text-pastel-purple mb-6">Nahrát novou fotku</h1>
            <p class="text-gray-700 mb-6">Vyplňte prosím tento formulář pro nahrání nové fotky.</p>
            <p class="text-gray-500 mb-6 text-sm">Nahrané fotky budou automaticky zkomprimovány na přibližně 500 KB se zachováním poměru stran i orientace.</p>

            <?php
            // Display error messages if any
            if (!empty($description_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $description_err . '</div>'; }
            if (!empty($date_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $date_err . '</div>'; }
            if (!empty($photo_err)) { echo '<div class="text-red-500 text-sm mb-2">' . $photo_err . '</div>'; }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="event_name" class="block text-gray-700 font-bold mb-2">Název události</label>
                    <input type="text" id="event_name" name="event_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($event_name_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $event_name; ?>">
                    <?php if (!empty($event_name_err)): ?><p class="text-red-500 text-xs italic"><?php echo $event_name_err; ?></p><?php endif; ?>
                </div>
                 <div>
                    <label for="date" class="block text-gray-700 font-bold mb-2">Datum události</label>
                    <input type="date" id="date" name="date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($date_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $date; ?>">
                    <?php if (!empty($date_err)): ?><p class="text-red-500 text-xs italic"><?php echo $date_err; ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="description" class="block text-gray-700 font-bold mb-2">Popisek (volitelné)</label>
                    <textarea id="description" name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($description_err)) ? 'border-red-500' : ''; ?>"><?php echo $description; ?></textarea>
                    <?php if (!empty($description_err)): ?><p class="text-red-500 text-xs italic"><?php echo $description_err; ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="photos" class="block text-gray-700 font-bold mb-2">Soubory fotek</label>
                    <input type="file" id="photos" name="photos[]" multiple class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($photo_err)) ? 'border-red-500' : ''; ?>">
                    <?php if (!empty($photo_err)): ?><p class="text-red-500 text-xs italic"><?php echo $photo_err; ?></p><?php endif; ?>
                </div>
                <div class="flex items-center justify-between">
                    <input type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer" value="Nahrát fotky">
                    <a href="./dashboard.php" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800">Zrušit</a>
                </div>
            </form>
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