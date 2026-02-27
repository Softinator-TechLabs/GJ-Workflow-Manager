<?php
require_once( 'c:/xampp/htdocs/outliny/wp-load.php' );
require_once( 'c:/xampp/htdocs/outliny/wp-content/plugins/sad-workflow-manager/includes/class-sad-logger.php' );

echo "Testing SAD_Logger...\n";
SAD_Logger::log( "Test message from verification script." );

$log_file = plugin_dir_path( dirname( __FILE__ ) . '/../dummy' ) . 'debug.log'; 
// plugin_dir_path logic check
// dirname(__FILE__) is .../tests (if I put it in tests)
// Let's just use the class logic.

$reflector = new ReflectionClass('SAD_Logger');
$class_file = $reflector->getFileName();
echo "Class file: $class_file\n";
$expected_log = plugin_dir_path( dirname( $class_file ) ) . 'debug.log';
echo "Expected log file: $expected_log\n";
echo "Realpath of Expected log file: " . realpath($expected_log) . "\n";
echo "Dirname of class file: " . dirname($class_file) . "\n";
echo "Plugin Dir Path of dirname: " . plugin_dir_path(dirname($class_file)) . "\n";

if ( file_exists( $expected_log ) ) {
    echo "Log file exists!\n";
    echo "Content:\n" . file_get_contents( $expected_log );
} else {
    echo "Log file NOT created.\n";
}
