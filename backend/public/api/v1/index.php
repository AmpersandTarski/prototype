<?php

// Intelligent path resolution for different environments
function getFrameworkPath() {
    $baseFile = __FILE__;
    
    // Try different dirname levels and check if framework.php exists
    // Level 4: When copied to html/api/v1 (/var/www/html/api/v1 -> /var/www)
    // Level 3: Source file location (/var/www/backend/public/api/v1 -> /var/www/backend)
    $levels = [
        4 => dirname($baseFile, 4) . '/backend/bootstrap/framework.php',  // When copied to html
        3 => dirname($baseFile, 3) . '/bootstrap/framework.php'           // Source file location
    ];
    
    foreach ($levels as $level => $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Fallback: if neither works, try to detect based on current path structure
    $currentDir = dirname($baseFile, 3);
    if (strpos($currentDir, '/var/www/backend') !== false) {
        // We're in source location - use level 3
        return dirname($baseFile, 3) . '/bootstrap/framework.php';
    } else {
        // We're in copied html location - use level 4
        return dirname($baseFile, 4) . '/backend/bootstrap/framework.php';
    }
}

require_once(getFrameworkPath());
