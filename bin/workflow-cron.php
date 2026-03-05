<?php
/**
 * Standalone script to trigger the workflow daily check.
 * This can be run via crontab:
 * php /home/runcloud/webapps/GlobalJournalsOrg/public_html/wp-content/plugins/gj-workflow-manager-main/bin/workflow-cron.php
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

if (!file_exists($wp_load_path)) {
    die("Error: wp-load.php not found at $wp_load_path\n");
}

// Set defining constant to prevent header output if needed, though CLI should be fine
define('WP_USE_THEMES', false);

// Increase limits for large datasets (approx 18k+ articles)
if ( php_sapi_name() === 'cli' ) {
    ini_set('memory_limit', '1024M'); // 1GB should be plenty for metadata processing
    set_time_limit(0); // No time limit for CLI
}

require_once($wp_load_path);

// Check if user has permission to run this (CLI only)
if (php_sapi_name() !== 'cli') {
    wp_die("Error: This script can only be run from the command line.");
}

error_log("SAD Workflow CLI: Starting manual trigger via " . __FILE__);
echo "Starting SAD Workflow Daily Check...\n";

// Trigger the action
do_action('sad_workflow_daily_check');

echo "Finished SAD Workflow Daily Check.\n";
