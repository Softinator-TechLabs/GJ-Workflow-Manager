<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * administrative area. This file also includes all of the plugin dependencies.
 *
 * @link              https://outliny.com
 * @since             1.0.0
 * @package           SAD_Workflow_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       SAD Workflow Manager
 * Plugin URI:        https://outliny.com
 * Description:       Automated tagging and workflow management for scholarly articles.
 * Version:           1.0.0
 * Author:            Outliny Team
 * Author URI:        https://outliny.com
 * Text Domain:       sad-workflow-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SAD_WORKFLOW_MANAGER_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-sad-workflow-activator.php
 */
function activate_sad_workflow_manager() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-sad-workflow-activator.php';
	SAD_Workflow_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-sad-workflow-deactivator.php
 */
function deactivate_sad_workflow_manager() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-sad-workflow-deactivator.php';
	SAD_Workflow_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_sad_workflow_manager' );
register_deactivation_hook( __FILE__, 'deactivate_sad_workflow_manager' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-sad-workflow-manager.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_sad_workflow_manager() {

	$plugin = new SAD_Workflow_Manager();
	$plugin->run();

}
run_sad_workflow_manager();

add_action( 'save_post', function($post_id) {
    if ( class_exists( 'SAD_Logger' ) ) {
        SAD_Logger::log( "GLOBAL DEBUG: save_post fired for ID $post_id" );
    }
});
