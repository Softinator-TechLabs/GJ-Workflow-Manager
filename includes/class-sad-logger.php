<?php

/**
 * Custom Logger Class
 * Writes to debug.log in the plugin directory.
 */
class SAD_Logger {

    /**
     * Log a message to the debug file.
     *
     * @param mixed $message The message to log. Arrays/Objects will be print_r'd.
     */
    public static function log( $message ) {
        // Force write to plugin root
        $log_file = plugin_dir_path( dirname( __FILE__ ) ) . 'debug_engine.log';

        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }

        $timestamp = date( '[Y-m-d H:i:s] ' );
        $formatted_message = $timestamp . $message . PHP_EOL;

        // Append to file
        file_put_contents( $log_file, $formatted_message, FILE_APPEND );
    }
}
