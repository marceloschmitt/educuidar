<?php
/**
 * Initialize application
 */

session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../models/',
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
?>

