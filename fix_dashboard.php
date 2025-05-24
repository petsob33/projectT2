<?php
// This is a script to fix the dashboard.php file
// Run this script to fix the unmatched closing brace issue

$dashboard_file = __DIR__ . '/admin/dashboard.php';
$content = file_get_contents($dashboard_file);

// Fix the unmatched closing brace by removing it
$fixed_content = str_replace("    });
}", "    });
", $content);

// Save the fixed content back to the file
file_put_contents($dashboard_file, $fixed_content);

echo "Dashboard file fixed successfully!\n";
?>