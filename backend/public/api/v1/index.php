<?php

// Intelligent path resolution for different environments
function getFrameworkPath() {
    $baseFile = __FILE__;
    
    // Try different dirname levels and check if framework.php exists
    // Level 5: Dev container environment (/var/www/backend/public/api/v1 -> /var/www)
    // Level 3: Normal environment (different structure)
    $levels = [
        5 => dirname($baseFile, 5) . '/backend/bootstrap/framework.php',  // Dev container environment
        3 => dirname($baseFile, 3) . '/backend/bootstrap/framework.php'   // Normal environment
    ];
    
    foreach ($levels as $level => $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Fallback: if neither works, try to detect based on current path structure
    $currentDir = dirname($baseFile, 4); // Get to backend level
    if (strpos($currentDir, '/var/www/backend') !== false) {
        // We're in dev container structure - use level 5
        return dirname($baseFile, 5) . '/backend/bootstrap/framework.php';
    } else {
        // We're in normal structure - use level 3
        return dirname($baseFile, 3) . '/backend/bootstrap/framework.php';
    }
}

require_once(getFrameworkPath());
