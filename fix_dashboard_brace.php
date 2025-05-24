<?php
// This script specifically fixes the unmatched closing brace in dashboard.php

$dashboard_file = __DIR__ . '/admin/dashboard.php';
$content = file_get_contents($dashboard_file);

// Find the line with the unmatched closing brace
$lines = explode("\n", $content);
$found = false;

foreach ($lines as $i => $line) {
    if (trim($line) === '}' && $i > 180 && $i < 200) {
        echo "Found unmatched closing brace at line " . ($i + 1) . "\n";
        unset($lines[$i]);
        $found = true;
        break;
    }
}

if ($found) {
    // Save the fixed content back to the file
    $fixed_content = implode("\n", $lines);
    file_put_contents($dashboard_file, $fixed_content);
    echo "Dashboard file fixed successfully!\n";
} else {
    echo "Could not find the unmatched closing brace.\n";
    
    // Alternative approach: manually remove line 191
    $lines = explode("\n", $content);
    if (isset($lines[190]) && trim($lines[190]) === '}') {
        unset($lines[190]);
        $fixed_content = implode("\n", $lines);
        file_put_contents($dashboard_file, $fixed_content);
        echo "Removed line 191 (index 190) which contained a closing brace.\n";
    } else {
        echo "Line 191 does not contain just a closing brace. Manual inspection needed.\n";
        echo "Content of line 191: " . (isset($lines[190]) ? $lines[190] : "Line does not exist") . "\n";
    }
}
?>