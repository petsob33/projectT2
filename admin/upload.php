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

// Define variables and initialize with empty values
$description = $date = "";
$description_err = $photo_err = $date_err = "";

// Function to compress image while preserving orientation and aspect ratio
function compressImage($source, $destination, $quality, $target_size = 500000) {
    // Get image information including EXIF data
    $info = getimagesize($source);
    $exif = @exif_read_data($source);
    
    if ($info['mime'] == 'image/jpeg' || $info['mime'] == 'image/jpg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
    } else {
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
    
    // Try just quality reduction first (without resizing)
    imagejpeg($image, $destination, $curr_quality);
    
    // Check if target size is reached
    $current_size = filesize($destination);
    
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
    return true;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Prosím, zadejte popisek k fotce.";
    } else {
        $description = trim($_POST["description"]);
    }

    // Validate date
    if (empty(trim($_POST["date"]))) {
        $date_err = "Prosím, vyberte datum pro fotku.";
    } else {
        $date = trim($_POST["date"]);
        // Optional: Add more date validation if needed (e.g., valid date format)
    }


    // Check if file was uploaded without errors
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0) {
        $allowed_types = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $file_name = $_FILES["photo"]["name"];
        $file_type = $_FILES["photo"]["type"];
        $file_size = $_FILES["photo"]["size"];

        // Verify file extension
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed_types)) {
            $photo_err = "Chyba: Prosím, vyberte platný formát souboru (JPG, JPEG, GIF, PNG).";
        }

        // Verify file size - 10MB maximum (initial upload size limit)
        $maxsize = 10 * 1024 * 1024;
        if ($file_size > $maxsize) {
            $photo_err = "Chyba: Velikost souboru je větší než povolený limit (10MB).";
        }

        // Verify MIME type of the file
        if (!in_array($file_type, $allowed_types)) {
            $photo_err = "Chyba: Při nahrávání souboru došlo k problému. Zkuste to prosím znovu.";
        }

        // Check if there were no upload errors and no description or date errors
        if (empty($photo_err) && empty($description_err) && empty($date_err)) {
            // Generate a unique filename
            $new_filename = uniqid() . ".jpg"; // Always save as JPG after compression
            $upload_path = "../uploads/" . $new_filename;
            $temp_file = $_FILES["photo"]["tmp_name"];

            // Compress the image before saving
            if (compressImage($temp_file, $upload_path, 85, 500000)) { // Target size: 500KB (500,000 bytes)
                // File uploaded and compressed successfully, now determine user or pair
                $user_id = $_SESSION['id'];
                $pair_id = null;

                // Check if the user is part of a pair
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

                // Insert into database
                if ($pair_id !== null) {
                    // Insert with pair_id and the uploader's user_id
                    $sql = "INSERT INTO photos (filename, description, date, user_id, pair_id) VALUES (:filename, :description, :date, :user_id, :pair_id)";
                } else {
                    // Insert with user_id
                    $sql = "INSERT INTO photos (filename, description, date, user_id, pair_id) VALUES (:filename, :description, :date, :user_id, NULL)";
                }


                if ($stmt = $pdo->prepare($sql)) {
                    // Bind parameters
                    $stmt->bindParam(":filename", $new_filename, PDO::PARAM_STR);
                    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
                    $stmt->bindParam(":date", $date, PDO::PARAM_STR); // Bind date to date

                    if ($pair_id !== null) {
                         $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT); // Bind user_id
                         $stmt->bindParam(":pair_id", $pair_id, PDO::PARAM_INT); // Bind pair_id
                    } else {
                         $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                    }


                    // Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        // Redirect to dashboard
                        header("location: ./dashboard.php");
                        exit();
                    } else {
                        echo "Chyba: Při vkládání do databáze došlo k problému. Zkuste to prosím znovu později.";
                        // Optionally delete the uploaded file if database insertion fails
                        unlink($upload_path);
                    }
                }
                unset($stmt);
            } else {
                $photo_err = "Chyba: Při kompresi souboru došlo k problému. Zkuste to prosím znovu.";
            }
        }
    } else {
        // Handle cases where no file was uploaded or there was an upload error
        if ($_FILES["photo"]["error"] != UPLOAD_ERR_NO_FILE) {
             $photo_err = "Chyba: " . $_FILES["photo"]["error"];
        } else {
             $photo_err = "Prosím, vyberte fotku k nahrání.";
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
        <div class="text-3xl font-bold text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></div>
        <div class="hamburger-menu-icon text-pastel-purple text-3xl cursor-pointer">&#9776;</div>
    </header>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 p-4">
        <h2 class="text-xl font-bold mb-4">Menu</h2>
        <ul>
            <li class="mb-2"><a href="../public/index.php" class="text-gray-700 hover:text-pastel-purple">Hlavní stránka</a></li>
            <li class="mb-2"><a href="../app/memories.php" class="text-gray-700 hover:text-pastel-purple"><?php echo $_SESSION['username'] . " a " . $partner_username; ?></a></li>
            <li class="mb-2"><a href="../app/pair_requests.php" class="text-gray-700 hover:text-pastel-purple">Žádosti o párování</a></li>
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
                    <label for="description" class="block text-gray-700 font-bold mb-2">Popisek</label>
                    <textarea id="description" name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($description_err)) ? 'border-red-500' : ''; ?>"><?php echo $description; ?></textarea>
                    <?php if (!empty($description_err)): ?><p class="text-red-500 text-xs italic"><?php echo $description_err; ?></p><?php endif; ?>
                </div>
                 <div>
                    <label for="date" class="block text-gray-700 font-bold mb-2">Datum</label>
                    <input type="date" id="date" name="date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($date_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $date; ?>">
                    <?php if (!empty($date_err)): ?><p class="text-red-500 text-xs italic"><?php echo $date_err; ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="photo" class="block text-gray-700 font-bold mb-2">Soubor fotky</label>
                    <input type="file" id="photo" name="photo" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($photo_err)) ? 'border-red-500' : ''; ?>">
                    <?php if (!empty($photo_err)): ?><p class="text-red-500 text-xs italic"><?php echo $photo_err; ?></p><?php endif; ?>
                </div>
                <div class="flex items-center justify-between">
                    <input type="submit" class="bg-pastel-pink text-pastel-purple font-bold py-2 px-4 rounded-xl shadow hover:opacity-90 transition duration-300 cursor-pointer" value="Nahrát fotku">
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