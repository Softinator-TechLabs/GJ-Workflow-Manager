<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://outliny.com
 * @since      1.0.0
 *
 * @package    SAD_Workflow_Manager
 * @subpackage SAD_Workflow_Manager/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    SAD_Workflow_Manager
 * @subpackage SAD_Workflow_Manager/includes
 * @author     Outliny Team <info@outliny.com>
 */
class SAD_Workflow_Manager {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SAD_Workflow_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SAD_WORKFLOW_MANAGER_VERSION' ) ) {
			$this->version = SAD_WORKFLOW_MANAGER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'sad-workflow-manager';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_taxonomy_hooks();
        $this->define_integration_hooks();
        $this->define_workflow_hooks();
        $this->define_webhook_hooks();

		new SAD_Withdrawal_Handler();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - SAD_Workflow_Loader. Orchestrates the hooks of the plugin.
     * - SAD_Workflow_Taxonomy. Registers the custom taxonomy.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-workflow-loader.php';

        /**
		 * The class responsible for defining custom taxonomies.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-workflow-taxonomy.php';

        /**
		 * The class responsible for managing rules model.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-rule-model.php';

        /**
		 * The class responsible for evaluating rules.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-rule-engine.php';

        /**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-admin.php';

        /**
		 * The class responsible for handling Outliny integration.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-integration.php';

        /**
		 * The class responsible for displaying activity logs.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-activity-log.php';

        /**
		 * The class responsible for custom debugging.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-logger.php';

        /**
		 * The class responsible for workflow cron tasks.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-workflow-cron.php';

        /**
		 * The class responsible for webhook dispatcher.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-webhook-dispatcher.php';

        /**
		 * The class responsible for workflow dashboard widget.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-workflow-dashboard.php';

		/**
		 * The class responsible for article withdrawal.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-sad-withdrawal-handler.php';


		$this->loader = new SAD_Workflow_Loader();

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new SAD_Workflow_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_page' );
        $this->loader->add_action( 'wp_ajax_sad_save_rules', $plugin_admin, 'ajax_save_rules' );

        // Add Progress Tags to Title
        $this->loader->add_filter( 'display_post_states', $plugin_admin, 'add_progress_tags_to_title', 10, 2 );

	}

    /**
	 * Register all of the hooks related to custom taxonomies.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_taxonomy_hooks() {

		$plugin_taxonomy = new SAD_Workflow_Taxonomy();

		$this->loader->add_action( 'init', $plugin_taxonomy, 'register_article_progress_taxonomy' );

        $rule_engine = new SAD_Rule_Engine();
        // Use transition_post_status to catch status changes
        $this->loader->add_action( 'transition_post_status', $rule_engine, 'run_rules_on_transition', 20, 3 );
        // $this->loader->add_action( 'save_post', $rule_engine, 'run_rules', 20, 2 );
        // $this->loader->add_action( 'pods_api_post_save_pod_item', $rule_engine, 'run_rules', 20, 2 );
	}

    /**
	 * Register all of the hooks related to integration and logging.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_integration_hooks() {

		$plugin_integration = new SAD_Integration();
		$this->loader->add_action( 'outliny_session_completed', $plugin_integration, 'handle_outliny_completion' );

        // Always listen for ticket status changes (not just during Outliny sessions)
        $this->loader->add_action( 'stt_ticket_status_changed', $plugin_integration, 'handle_ticket_status_change', 10, 4 );

        $plugin_activity_log = new SAD_Activity_Log();
        $this->loader->add_action( 'add_meta_boxes', $plugin_activity_log, 'add_meta_box' );

	}

    /**
	 * Register all of the hooks related to workflow tracking and dashboard.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_workflow_hooks() {

		$workflow_cron = new SAD_Workflow_Cron();
		$this->loader->add_action( 'init', $workflow_cron, 'init' );

        $workflow_dashboard = new SAD_Workflow_Dashboard();
        $this->loader->add_action( 'init', $workflow_dashboard, 'init' );

	}

    /**
	 * Register all of the hooks related to webhook dispatching.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_webhook_hooks() {

		$webhook_dispatcher = new SAD_Webhook_Dispatcher();
		$this->loader->add_action( 'add_meta_boxes', $webhook_dispatcher, 'add_meta_box' );
        $this->loader->add_action( 'wp_ajax_sad_send_webhook', $webhook_dispatcher, 'ajax_send_webhook' );
		
		// Trigger webhook after Quick Submit article creation (fires with complete metadata and files)
		$this->loader->add_action( 'sad_after_quick_submit', $webhook_dispatcher, 'trigger_on_quick_submit', 10, 3 );

		// Trigger webhook after Admin creation
		$this->loader->add_action( 'save_post_scholarly_article', $webhook_dispatcher, 'trigger_on_save', 10, 3 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    SAD_Workflow_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
