<?php
/**
 * Clear Files & Session
 * Removes all uploaded/generated files and resets session
 */

session_start();

// Clear all files from folders
$folders = ['uploads', 'results', 'pods'];

foreach ($folders as $folder) {
    $path = __DIR__ . "/$folder";
    if (is_dir($path)) {
        $files = glob("$path/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

// Destroy session
session_destroy();

// Redirect to index
header("Location: index.php");
exit;