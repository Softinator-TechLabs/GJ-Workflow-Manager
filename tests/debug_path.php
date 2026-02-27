<?php
// Load WordPress environment if possible, or just mock what we need
require_once( 'c:/xampp/htdocs/outliny/wp-load.php' );
// require_once dirname(__DIR__) . '/includes/class-sad-logger.php'; // Loaded by plugin ideally, but manual is fine for isolation

echo "Testing SAD_Logger Path Resolution...\n";
echo "Dirname: " . dirname(__FILE__) . "\n";
echo "Logger File: " . dirname(__DIR__) . '/includes/class-sad-logger.php' . "\n";

// Replicate logic
$log_file = plugin_dir_path( dirname( dirname(__DIR__) . '/includes/class-sad-logger.php' ) ) . 'debug_engine.log';
// Wait, `plugin_dir_path` is a WP function. If I run this via CLI without WP, it fails.
// Let's use the exact code from class-sad-logger.php but mock plugin_dir_path if needed.

// WP Loaded. Mocks removed.

$logger_path = plugin_dir_path( dirname( dirname(__DIR__) . '/includes/class-sad-logger.php' ) ) . 'debug_engine.log';
echo "Calculated Path: " . $logger_path . "\n";

// Now try to verify where the ACTUAL class thinks it is
SAD_Logger::log("Test from debug_path.php");
echo "Logged message.\n";

if ( file_exists( $logger_path ) ) {
    echo "File found at calculated path!\n";
    echo "Size: " . filesize($logger_path) . "\n";
} else {
    echo "File NOT found at calculated path.\n";
    // Search nearby
    $files = glob( dirname(__DIR__) . '/*.log' );
    print_r($files);
}
