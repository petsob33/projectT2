<?php
// Zobrazíme informace o PHP
echo "<h1>PHP Info</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Zkontrolujeme, zda je rozšíření GD nainstalováno
if (extension_loaded('gd')) {
    echo "<p style='color:green'>GD rozšíření je nainstalováno.</p>";
    
    // Získáme informace o GD
    $gd_info = gd_info();
    echo "<h2>GD Info</h2>";
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
    
    // Zkusíme vytvořit a uložit jednoduchý obrázek
    echo "<h2>Test vytvoření a uložení obrázku</h2>";
    
    // Vytvoříme obrázek
    $image = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $bg);
    
    // Cesta k souboru
    $file_path = "./uploads/test_image.jpg";
    
    // Pokusíme se uložit obrázek
    if (imagejpeg($image, $file_path, 90)) {
        echo "<p style='color:green'>Obrázek byl úspěšně vytvořen a uložen do: $file_path</p>";
        echo "<p>Velikost souboru: " . filesize($file_path) . " bajtů</p>";
        echo "<img src='$file_path' alt='Test Image'>";
    } else {
        echo "<p style='color:red'>Nepodařilo se uložit obrázek do: $file_path</p>";
        echo "<p>Chyba: " . error_get_last()['message'] . "</p>";
    }
    
    // Uvolníme paměť
    imagedestroy($image);
    
    // Zkusíme vytvořit testovací textový soubor
    echo "<h2>Test vytvoření textového souboru</h2>";
    $text_file_path = "./uploads/test_text.txt";
    $content = "Test zápisu do adresáře uploads: " . date('Y-m-d H:i:s');
    
    if (file_put_contents($text_file_path, $content)) {
        echo "<p style='color:green'>Textový soubor byl úspěšně vytvořen a uložen do: $text_file_path</p>";
        echo "<p>Obsah souboru: " . file_get_contents($text_file_path) . "</p>";
    } else {
        echo "<p style='color:red'>Nepodařilo se vytvořit textový soubor: $text_file_path</p>";
        echo "<p>Chyba: " . error_get_last()['message'] . "</p>";
    }
    
} else {
    echo "<p style='color:red'>GD rozšíření není nainstalováno!</p>";
}

// Zkontrolujeme oprávnění adresáře uploads
echo "<h2>Kontrola adresáře uploads</h2>";
$uploads_dir = "./uploads";

if (file_exists($uploads_dir)) {
    echo "<p>Adresář uploads existuje.</p>";
    
    if (is_dir($uploads_dir)) {
        echo "<p>uploads je adresář.</p>";
        
        if (is_writable($uploads_dir)) {
            echo "<p style='color:green'>Adresář uploads je zapisovatelný.</p>";
        } else {
            echo "<p style='color:red'>Adresář uploads není zapisovatelný!</p>";
        }
        
        // Zobrazíme oprávnění
        $perms = fileperms($uploads_dir);
        $perms_str = sprintf("%o", $perms);
        echo "<p>Oprávnění adresáře uploads: $perms_str</p>";
        
        // Zobrazíme vlastníka
        $owner = posix_getpwuid(fileowner($uploads_dir));
        $group = posix_getgrgid(filegroup($uploads_dir));
        echo "<p>Vlastník adresáře uploads: " . $owner['name'] . ":" . $group['name'] . "</p>";
        
        // Zobrazíme obsah adresáře
        echo "<h3>Obsah adresáře uploads:</h3>";
        $files = scandir($uploads_dir);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                echo "<li>$file</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>uploads není adresář!</p>";
    }
} else {
    echo "<p style='color:red'>Adresář uploads neexistuje!</p>";
}
?>